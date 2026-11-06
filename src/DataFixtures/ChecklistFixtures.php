<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Answer;
use App\Entity\CheckList;
use App\Entity\CheckListField;
use App\Entity\CheckListFieldType;
use App\Entity\CheckListItem;
use App\Entity\Friendship;
use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserDetails;
use App\Service\CheckListService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Users, friendships and checklists with every kind of access, to try out who sees and fills in what.
 *
 * Load only these into an existing database (keeps everything that is there):
 *   php bin/console doctrine:fixtures:load --group=checklists --append
 *
 * All users have the password "checklist123". Checklist names are unique, so loading this twice fails.
 *
 * | User                  | Account          | Checklists / friends                                              |
 * |-----------------------|------------------|-------------------------------------------------------------------|
 * | anna@example.com      | active, owner    | owns a public, a friends-only, a private and an archived list     |
 * | bram@example.com      | active, owner    | friend of Anna; owns a public and a friends-only list             |
 * | chris@example.com     | active           | asked Anna to be friends (pending), so can't see her friends list |
 * | dewi@example.com      | active           | no friends: only sees public and "logged-in users" lists          |
 * | eva@example.com       | pending          | registered, waiting for approval: can't log in                    |
 * | frank@example.com     | retired          | old account: can't log in                                         |
 */
class ChecklistFixtures extends Fixture implements FixtureGroupInterface, OrderedFixtureInterface
{
    public const PASSWORD = 'checklist123';

    /** @var array<string, CheckListFieldType> */
    private array $fieldTypes = [];

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private CheckListService $checkListService,
    ) {
    }

    public static function getGroups(): array
    {
        return ['checklists'];
    }

    /** After AppFixtures (which isn't ordered, so it goes first) in a full load: it creates the permissions */
    public function getOrder(): int
    {
        return 10;
    }

    public function load(ObjectManager $manager): void
    {
        $ownerRole = $this->getOwnerRole($manager);

        $anna = $this->createUser($manager, 'anna@example.com', 'Anna', 'de Vries', User::STATUS_ACTIVE, $ownerRole);
        $bram = $this->createUser($manager, 'bram@example.com', 'Bram', 'Jansen', User::STATUS_ACTIVE, $ownerRole);
        $chris = $this->createUser($manager, 'chris@example.com', 'Chris', 'Bakker', User::STATUS_ACTIVE);
        $dewi = $this->createUser($manager, 'dewi@example.com', 'Dewi', 'Visser', User::STATUS_ACTIVE);
        $this->createUser($manager, 'eva@example.com', 'Eva', 'Smit', User::STATUS_PENDING);
        $this->createUser($manager, 'frank@example.com', 'Frank', 'Mulder', User::STATUS_RETIRED);

        // Anna and Bram are friends; Chris asked Anna, but she hasn't answered yet
        $manager->persist((new Friendship($anna, $bram))->accept());
        $manager->persist(new Friendship($chris, $anna));
        $manager->flush();

        // Site checklists (no owner, managed by admins)
        $survey = $this->createCheckList($manager, 'Enquête: favoriete seizoen', CheckList::ACCESS_PUBLIC, null);
        $this->field($manager, $survey, 'Naam', 'text', required: true);
        $this->field($manager, $survey, 'Seizoen', 'radio', ['lente' => 'Lente', 'zomer' => 'Zomer', 'herfst' => 'Herfst', 'winter' => 'Winter'], required: true);
        $this->field($manager, $survey, 'Toelichting', 'textarea');
        $this->items($manager, $survey, [
            [null, ['field_naam' => 'Bezoeker', 'field_seizoen' => 'zomer']],
            [$dewi, ['field_naam' => 'Dewi', 'field_seizoen' => 'herfst', 'field_toelichting' => 'Mooie kleuren in het bos.']],
            [$chris, ['field_naam' => 'Chris', 'field_seizoen' => 'winter']],
        ]);

        $members = $this->createCheckList($manager, 'Aanmelden clubdag', CheckList::ACCESS_USER, null);
        $this->field($manager, $members, 'Naam', 'text', required: true);
        $this->field($manager, $members, 'Datum', 'date', options: ['dateFormat' => 'dd-MM-yyyy'], required: true);
        $this->field($manager, $members, 'Lunch', 'checkbox', ['1' => 'Ja']);
        $this->items($manager, $members, [
            [$anna, ['field_naam' => 'Anna', 'field_datum' => '14-03-2027', 'field_lunch' => ['1']]],
            [$dewi, ['field_naam' => 'Dewi', 'field_datum' => '14-03-2027']],
        ]);

        // Anna's checklists
        $movies = $this->createCheckList($manager, 'Anna: films om te kijken', CheckList::ACCESS_PUBLIC, $anna);
        $this->field($manager, $movies, 'Titel', 'text', required: true, showInOverview: true);
        $this->field($manager, $movies, 'Cijfer', 'select', ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5'], showInOverview: true);
        $this->field($manager, $movies, 'Gezien', 'checkbox', ['1' => 'Ja'], showInOverview: true);
        $this->items($manager, $movies, [
            [$anna, ['field_titel' => 'Arrival', 'field_cijfer' => '5', 'field_gezien' => ['1']]],
            [$anna, ['field_titel' => 'Dune: Part Two']],
            [$bram, ['field_titel' => 'Past Lives', 'field_cijfer' => '4', 'field_gezien' => ['1']]],
        ]);

        $groceries = $this->createCheckList($manager, 'Anna: weekendje weg (vrienden)', CheckList::ACCESS_FRIENDS, $anna);
        $this->field($manager, $groceries, 'Wat', 'text', required: true, showInOverview: true);
        $this->field($manager, $groceries, 'Wie neemt het mee', 'select', ['anna' => 'Anna', 'bram' => 'Bram'], showInOverview: true);
        $this->items($manager, $groceries, [
            [$anna, ['field_wat' => 'Tent', 'field_wie_neemt_het_mee' => 'anna']],
            [$bram, ['field_wat' => 'Gasbrander', 'field_wie_neemt_het_mee' => 'bram']],
        ]);

        $private = $this->createCheckList($manager, 'Anna: privé cadeaulijst', CheckList::ACCESS_ADMIN, $anna);
        $this->field($manager, $private, 'Voor wie', 'text', required: true, showInOverview: true);
        $this->field($manager, $private, 'Cadeau', 'text', showInOverview: true);
        $this->field($manager, $private, 'Gekocht', 'checkbox', ['1' => 'Ja'], showInOverview: true);
        $this->items($manager, $private, [
            [$anna, ['field_voor_wie' => 'Bram', 'field_cadeau' => 'Boek', 'field_gekocht' => ['1']]],
            [$anna, ['field_voor_wie' => 'Mam', 'field_cadeau' => 'Plant']],
        ]);

        $archived = $this->createCheckList($manager, 'Anna: vakantie 2025 (gearchiveerd)', CheckList::ACCESS_PUBLIC, $anna);
        $this->field($manager, $archived, 'Bestemming', 'text', required: true, showInOverview: true);
        $this->items($manager, $archived, [[$anna, ['field_bestemming' => 'Texel']]]);
        $archived->setDeleted(true);

        // Bram's checklists
        $books = $this->createCheckList($manager, 'Bram: leesclub', CheckList::ACCESS_PUBLIC, $bram);
        $this->field($manager, $books, 'Boek', 'text', required: true, showInOverview: true);
        $this->field($manager, $books, 'Genres', 'checkbox', ['roman' => 'Roman', 'thriller' => 'Thriller', 'non-fictie' => 'Non-fictie'], showInOverview: true);
        $this->items($manager, $books, [
            [$bram, ['field_boek' => 'De avond is ongemak', 'field_genres' => ['roman']]],
            [$dewi, ['field_boek' => 'Sapiens', 'field_genres' => ['non-fictie']]],
        ]);

        $bramFriends = $this->createCheckList($manager, 'Bram: klusdag (vrienden)', CheckList::ACCESS_FRIENDS, $bram);
        $this->field($manager, $bramFriends, 'Klus', 'text', required: true, showInOverview: true);
        $this->field($manager, $bramFriends, 'Tijd', 'time', showInOverview: true);
        $this->items($manager, $bramFriends, [[$anna, ['field_klus' => 'Schutting verven', 'field_tijd' => '10:00']]]);

        $manager->flush();

        // AnswerGiven records are derived from the item content, like when an item is saved in the app
        foreach ([$survey, $members, $movies, $groceries, $private, $archived, $books, $bramFriends] as $checkList) {
            foreach ($checkList->getCheckListItems() as $item) {
                $this->checkListService->syncItemAnswers($item, $checkList);
            }
        }
    }

    /** A role that may make and manage its own checklists (admin.access is needed to open the admin pages) */
    private function getOwnerRole(ObjectManager $manager): Role
    {
        $role = $manager->getRepository(Role::class)->findOneBy(['name' => 'ROLE_CHECKLIST_OWNER']);
        if ($role) {
            return $role;
        }

        $role = (new Role())->setName('ROLE_CHECKLIST_OWNER')->setDescription('Makes and manages own checklists');
        foreach (['admin.access' => 'Open the admin', 'checklist.own' => 'Make checklists and manage your own (from the profile page)'] as $name => $description) {
            $permission = $manager->getRepository(Permission::class)->findOneBy(['name' => $name]);
            if (!$permission) {
                $permission = (new Permission())->setName($name)->setDescription($description);
                $manager->persist($permission);
            }
            $role->addPermission($permission);
        }
        $manager->persist($role);

        return $role;
    }

    private function createUser(ObjectManager $manager, string $email, string $firstName, string $lastName, int $status, ?Role $role = null): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFullName($firstName . ' ' . $lastName);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::PASSWORD));
        $user->setStatus($status);
        $user->markEmailVerified();
        if ($role) {
            $user->addRole($role);
        }

        $details = new UserDetails();
        $details->setFirstName($firstName);
        $details->setLastName($lastName);
        $details->setUser($user);
        $user->setDetails($details);

        $manager->persist($user);
        $manager->persist($details);

        return $user;
    }

    private function createCheckList(ObjectManager $manager, string $name, string $access, ?User $owner): CheckList
    {
        $checkList = (new CheckList())->setName($name)->setAccess($access)->setOwner($owner);
        $checkList->setCreatedBy($owner);
        $manager->persist($checkList);

        return $checkList;
    }

    /**
     * @param array<int|string, string> $answers value => label, for radio/select/checkbox (PHP turns "1" keys into ints)
     * @param array<string, mixed>  $options field options as stored in the options JSON
     */
    private function field(
        ObjectManager $manager,
        CheckList $checkList,
        string $name,
        string $formType,
        array $answers = [],
        array $options = [],
        bool $required = false,
        bool $showInOverview = false,
    ): CheckListField {
        $field = new CheckListField();
        $field->setName($name);
        $field->setFormFieldName(strtolower(str_replace(' ', '_', $name)));
        $field->setFieldType($this->getFieldType($manager, $formType));
        $field->setOrder($checkList->getAllCheckListFields()->count() + 1);
        $field->setRequired($required);
        $field->setShowInOverview($showInOverview);
        $field->setOptionsAsArray($options);
        $field->setCheckList($checkList);
        $checkList->addCheckListField($field);
        foreach ($answers as $value => $label) {
            $answer = (new Answer())->setValue((string) $value)->setLabel($label);
            $manager->persist($answer);
            $field->addAnswer($answer);
        }
        $manager->persist($field);

        return $field;
    }

    /** @param list<array{0: User|null, 1: array<string, mixed>}> $items [created by, item content] */
    private function items(ObjectManager $manager, CheckList $checkList, array $items): void
    {
        foreach ($items as [$createdBy, $content]) {
            $item = new CheckListItem();
            $item->setCheckList($checkList);
            $item->setItemContent($content);
            $item->setCreatedBy($createdBy);
            $checkList->addCheckListItem($item);
            $manager->persist($item);
        }
    }

    private function getFieldType(ObjectManager $manager, string $formType): CheckListFieldType
    {
        if (!isset($this->fieldTypes[$formType])) {
            $this->fieldTypes[$formType] = $manager->getRepository(CheckListFieldType::class)->findOneBy(['formType' => $formType])
                ?? (new CheckListFieldType())->setName(ucfirst($formType))->setFormType($formType);
            $manager->persist($this->fieldTypes[$formType]);
        }

        return $this->fieldTypes[$formType];
    }
}
