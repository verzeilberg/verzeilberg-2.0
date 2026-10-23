<?php

declare(strict_types=1);

namespace App\Tests\Checklist;

use App\Entity\CheckList;
use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\RoutePermission;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Users with checklist.own make checklists from their profile and only manage their own */
class OwnChecklistTest extends WebTestCase
{
    use ChecklistTestTrait;

    protected function setUp(): void
    {
        $this->setUpChecklistTest();

        // Added by the migration; made sure of here so the test doesn't depend on it
        if ($this->em->getRepository(RoutePermission::class)->findOneBy(['routePattern' => 'admin_checklist_*']) === null) {
            $this->em->persist((new RoutePermission())->setRoutePattern('admin_checklist_*')->setPermission($this->permission('checklist.own')));
            $this->em->flush();
        }
    }

    protected function tearDown(): void
    {
        $this->tearDownChecklistTest();
        parent::tearDown();
    }

    public function testOwnerCreatesChecklistFromProfile(): void
    {
        $owner = $this->createOwner();
        $this->loginAs($owner);

        $crawler = $this->client->request('GET', '/profile');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Mijn checklists');

        $this->client->click($crawler->selectLink('Nieuwe checklist')->link());
        self::assertResponseIsSuccessful();
        $name = 'My packing list ' . uniqid();
        $this->client->submitForm('Checklist opslaan', ['check_list[name]' => $name, 'check_list[access]' => CheckList::ACCESS_USER]);
        self::assertResponseRedirects();

        $checkList = $this->em->getRepository(CheckList::class)->findOneBy(['name' => $name]);
        self::assertNotNull($checkList);
        self::assertTrue($checkList->isOwnedBy($owner));

        $this->client->request('GET', '/profile');
        self::assertSelectorTextContains('body', $name);
    }

    public function testOwnerCantTouchSomeoneElsesChecklist(): void
    {
        $other = $this->createCheckList('Not mine');
        $field = $this->createField($other, 'Name', 'text');
        $item = $this->createItem($other, []);
        $mine = $this->createCheckList('Mine')->setOwner($owner = $this->createOwner());
        $this->em->flush();
        $this->loginAs($owner);

        $this->client->request('GET', '/admin/checklist/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', $mine->getName());
        self::assertSelectorTextNotContains('table', $other->getName());

        foreach ([
            '/admin/checklist/' . $other->getId() . '/edit',
            '/admin/checklist/' . $other->getId(),
            '/admin/checklist/' . $other->getId() . '/export/csv',
            '/admin/checklist/' . $other->getId() . '/fields/new',
            '/admin/checklist/field/' . $field->getId() . '/edit',
            '/admin/checklist/item/' . $item->getId(),
            '/admin/checklist/item/' . $item->getId() . '/edit',
        ] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }

        $this->client->request('GET', '/admin/checklist/' . $mine->getId() . '/edit');
        self::assertResponseIsSuccessful();
    }

    public function testFieldTypesAndAnswerEditingAreForManagersOnly(): void
    {
        $this->loginAs($this->createOwner());

        $this->client->request('GET', '/admin/checklist/field-types');
        self::assertResponseStatusCodeSame(403);

        // Owners may see and add shared answers (for their choice fields), not change them
        $this->client->request('GET', '/admin/checklist/answers');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/checklist/answers/new');
        self::assertResponseIsSuccessful();
    }

    public function testManagerSeesEveryChecklist(): void
    {
        $other = $this->createCheckList('Someone else');
        $this->loginAs($this->createOwner(['checklist.manage']));

        $this->client->request('GET', '/admin/checklist/');
        self::assertSelectorTextContains('table', $other->getName());
        $this->client->request('GET', '/admin/checklist/' . $other->getId() . '/edit');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/checklist/field-types');
        self::assertResponseIsSuccessful();
    }

    public function testDuplicateBelongsToWhoeverDuplicates(): void
    {
        $owner = $this->createOwner();
        $original = $this->createCheckList('Original')->setOwner($owner);
        $this->em->flush();
        $this->loginAs($owner);

        $crawler = $this->client->request('GET', '/admin/checklist/');
        $token = $crawler->filter('[data-delete-url$="/' . $original->getId() . '/duplicate"]')->attr('data-csrf-token');
        $this->client->request('POST', '/admin/checklist/' . $original->getId() . '/duplicate', ['_token' => $token]);
        self::assertResponseIsSuccessful();

        self::assertCount(2, $this->em->getRepository(CheckList::class)->findBy(['owner' => $owner]));
    }

    public function testProfileWithoutPermissionsShowsNoChecklists(): void
    {
        $this->loginAs($this->createUser('ROLE_USER'));
        $this->client->request('GET', '/profile');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Mijn checklists');
    }

    public function testProfileExplainsWhenAdminAccessIsMissing(): void
    {
        $this->loginAs($this->createOwner([], withAdminAccess: false));
        $this->client->request('GET', '/profile');
        self::assertSelectorTextContains('body', 'admin.access');
    }

    /** @param list<string> $extraPermissions */
    private function createOwner(array $extraPermissions = [], bool $withAdminAccess = true): User
    {
        $role = (new Role())->setName('ROLE_TEST_OWNER_' . strtoupper(uniqid()));
        $this->em->persist($role);
        foreach (array_merge(['checklist.own'], $withAdminAccess ? ['admin.access'] : [], $extraPermissions) as $name) {
            $role->addPermission($this->permission($name));
        }

        $user = new User();
        $user->setEmail(uniqid('owner-test-') . '@example.com');
        $user->setFullName('Checklist Owner');
        $user->setPassword('not-used');
        $user->addRole($role);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function permission(string $name): Permission
    {
        $permission = $this->em->getRepository(Permission::class)->findOneBy(['name' => $name]);
        if ($permission === null) {
            $permission = (new Permission())->setName($name);
            $this->em->persist($permission);
            $this->em->flush();
        }

        return $permission;
    }
}
