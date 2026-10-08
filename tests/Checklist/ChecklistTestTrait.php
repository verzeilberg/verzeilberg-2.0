<?php

declare(strict_types=1);

namespace App\Tests\Checklist;

use App\Entity\Answer;
use App\Entity\CheckList;
use App\Entity\CheckListField;
use App\Entity\CheckListFieldType;
use App\Entity\CheckListItem;
use App\Entity\Role;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Builds checklist test data. Every test runs inside a database transaction that is rolled back afterwards,
 * so tests don't depend on each other or on the data in the test database.
 */
trait ChecklistTestTrait
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;
    protected Connection $connection;

    protected function setUpChecklistTest(): void
    {
        $this->client = static::createClient();
        // Keep one kernel (and so one database connection) for all requests of a test, so the rollback covers them
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->em->getConnection();
        $this->connection->beginTransaction();
    }

    protected function tearDownChecklistTest(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
    }

    protected function createCheckList(string $name = 'Test checklist', string $access = CheckList::ACCESS_ADMIN): CheckList
    {
        $checkList = (new CheckList())->setName($name . ' ' . uniqid())->setAccess($access);
        $this->em->persist($checkList);
        $this->em->flush();

        return $checkList;
    }

    /**
     * @param array<string, mixed> $options      field options (as stored in the options JSON)
     * @param array<int|string, string> $answers value => label, for radio/select/checkbox
     */
    protected function createField(
        CheckList $checkList,
        string $name,
        string $formType,
        array $options = [],
        bool $required = false,
        ?CheckListField $parent = null,
        array $answers = [],
    ): CheckListField {
        $field = new CheckListField();
        $field->setName($name);
        $field->setFormFieldName(strtolower(str_replace(' ', '_', $name)));
        $field->setFieldType($this->getFieldType($formType));
        $field->setOrder(count($this->em->getRepository(CheckListField::class)->findBy(['checkList' => $checkList])) + 1);
        $field->setRequired($required);
        $field->setOptionsAsArray($options);
        $field->setCheckList($checkList);
        foreach ($answers as $value => $label) {
            $answer = (new Answer())->setValue((string) $value)->setLabel($label);
            $this->em->persist($answer);
            $field->addAnswer($answer);
        }
        if ($parent) {
            $field->setParent($parent);
            $parent->addChild($field);
        }
        $this->em->persist($field);
        $this->em->flush();

        return $field;
    }

    /** @param array<string, mixed> $content */
    protected function createItem(CheckList $checkList, array $content, ?User $createdBy = null): CheckListItem
    {
        $item = new CheckListItem();
        $item->setCheckList($checkList);
        $item->setItemContent($content);
        $item->setCreatedBy($createdBy);
        $this->em->persist($item);
        $this->em->flush();

        return $item;
    }

    protected function createUser(string $roleName = 'ROLE_USER'): User
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => $roleName]);
        if (!$role) {
            $role = (new Role())->setName($roleName)->setDescription($roleName);
            $this->em->persist($role);
        }
        $user = new User();
        $user->setEmail(uniqid('checklist-test-') . '@example.com');
        $user->setFullName('Test ' . $roleName);
        $user->setPassword('not-used');
        $user->addRole($role);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function loginAs(User $user): void
    {
        // The firewall uses "context: primary_auth", so the session key is _security_primary_auth
        $this->client->loginUser($user, 'primary_auth');
    }

    /** @return array<string, mixed> */
    protected function storedContent(CheckListItem $item): array
    {
        return json_decode((string) $this->connection->fetchOne('SELECT item_content FROM checklist_items WHERE id = ?', [$item->getId()]), true) ?? [];
    }

    private function getFieldType(string $formType): CheckListFieldType
    {
        $type = $this->em->getRepository(CheckListFieldType::class)->findOneBy(['formType' => $formType]);
        if (!$type) {
            $type = (new CheckListFieldType())->setName(ucfirst($formType))->setFormType($formType);
            $this->em->persist($type);
            $this->em->flush();
        }

        return $type;
    }
}
