<?php

namespace App\Tests\Security;

use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\RoutePermission;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Who may open which admin page: ROLE_ADMIN everything, others need admin.access plus a route rule.
 * Every test runs inside a database transaction that is rolled back afterwards.
 */
class AdminAccessTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->em->getConnection();
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    public function testAdminCanOpenEverythingWithoutRules(): void
    {
        $this->login($this->createUser(['ROLE_ADMIN']));

        foreach (['/admin/', '/admin/blog/', '/admin/user/', '/admin/role/', '/admin/permission/', '/admin/route-permission/'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
        }
    }

    public function testManagementPagesShowRulesAndAccess(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $this->login($admin);
        $role = $this->createRole('ROLE_TEST_EDITOR', ['admin.access', 'blog.edit']);
        $rule = $this->createRule('admin_blog_*', 'blog.edit');

        $this->client->request('GET', '/admin/route-permission/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'admin_blog_*');
        // The overview says which role opens the blog section
        self::assertSelectorTextContains('.list-group', 'ROLE_TEST_EDITOR');

        foreach (['/admin/route-permission/' . $rule->getId() . '/edit', '/admin/role/' . $role->getId() . '/edit', '/admin/permission/', '/admin/user/' . $admin->getId() . '/edit', '/admin/role/new', '/admin/user/new'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
        }

        // Toggling needs the CSRF token
        $this->client->request('POST', '/admin/route-permission/' . $rule->getId() . '/toggle');
        self::assertResponseStatusCodeSame(403);
        self::assertTrue($rule->isEnabled());
    }

    public function testAnonymousVisitorIsSentToLogin(): void
    {
        $this->client->request('GET', '/admin/blog/');

        self::assertResponseRedirects('/login');
    }

    public function testUserWithoutAdminAccessCantEnterTheAdmin(): void
    {
        $this->login($this->createUser([$this->createRole('ROLE_TEST_PLAIN', ['blog.edit'])]));
        $this->createRule('admin_blog_*', 'blog.edit');

        $this->client->request('GET', '/admin/');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/blog/');
        self::assertResponseStatusCodeSame(403);
    }

    public function testRuleOpensOnlyItsSection(): void
    {
        $this->login($this->createUser([$this->createRole('ROLE_TEST_EDITOR', ['admin.access', 'blog.edit'])]));
        $this->createRule('admin_blog_*', 'blog.edit');

        $this->client->request('GET', '/admin/');
        self::assertResponseIsSuccessful();
        // The sidebar only shows what the user can open
        self::assertSelectorExists('.admin-sidebar a[href="/admin/blog/"]');
        self::assertSelectorNotExists('.admin-sidebar a[href="/admin/event/"]');
        self::assertSelectorNotExists('.admin-sidebar a[href="/admin/user/"]');

        $this->client->request('GET', '/admin/blog/');
        self::assertResponseIsSuccessful();

        // No rule for events: admins only, shown as the friendly "no access" page
        $this->client->request('GET', '/admin/event/');
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('main h2', 'Je hebt geen toegang tot deze pagina');

        $this->client->xmlHttpRequest('GET', '/admin/event/');
        self::assertResponseStatusCodeSame(403);
        self::assertJson((string) $this->client->getResponse()->getContent());
    }

    public function testUserManagementStaysAdminOnlyEvenWithARule(): void
    {
        $this->login($this->createUser([$this->createRole('ROLE_TEST_SNEAKY', ['admin.access', 'blog.edit'])]));
        // A too-wide rule must still not open user/role management
        $this->createRule('admin_*', 'blog.edit');

        $this->client->request('GET', '/admin/blog/');
        self::assertResponseIsSuccessful();
        foreach (['/admin/user/', '/admin/role/', '/admin/permission/', '/admin/route-permission/'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }
    }

    public function testMostSpecificRuleWinsAndMethodsLimitIt(): void
    {
        $this->login($this->createUser([$this->createRole('ROLE_TEST_VIEWER', ['admin.access', 'blog.view'])]));
        $this->createRule('admin_blog_*', 'blog.view');
        // Saving a new post needs blog.create, which this user lacks
        $this->createRule('admin_blog_new', 'blog.create', ['POST']);

        $this->client->request('GET', '/admin/blog/new');
        self::assertResponseIsSuccessful();
        $this->client->request('POST', '/admin/blog/new');
        self::assertResponseStatusCodeSame(403);
    }

    public function testDisabledRuleGivesNoAccess(): void
    {
        $this->login($this->createUser([$this->createRole('ROLE_TEST_EDITOR', ['admin.access', 'blog.edit'])]));
        $this->createRule('admin_blog_*', 'blog.edit')->setEnabled(false);
        $this->em->flush();

        $this->client->request('GET', '/admin/blog/');
        self::assertResponseStatusCodeSame(403);
    }

    public function testPermissionsAreInheritedFromParentRoles(): void
    {
        $base = $this->createRole('ROLE_TEST_BASE', ['admin.access', 'blog.edit']);
        $child = $this->createRole('ROLE_TEST_CHILD', []);
        $child->addParentRole($base);
        $this->em->flush();
        $this->login($this->createUser([$child]));
        $this->createRule('admin_blog_*', 'blog.edit');

        $this->client->request('GET', '/admin/blog/');
        self::assertResponseIsSuccessful();
    }

    public function testInactiveUserCantLogIn(): void
    {
        $user = $this->createUser(['ROLE_ADMIN']);
        $user->setStatus(User::STATUS_RETIRED);
        $user->setPassword(static::getContainer()->get('security.user_password_hasher')->hashPassword($user, 'secret123'));
        $this->em->flush();

        $this->client->request('GET', '/login');
        $this->client->submitForm('Inloggen', ['_username' => $user->getEmail(), '_password' => 'secret123']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert', 'gedeactiveerd');
    }

    public function testRoleFormRejectsInheritanceLoop(): void
    {
        $this->login($this->createUser(['ROLE_ADMIN']));
        $a = $this->createRole('ROLE_TEST_A', []);
        $b = $this->createRole('ROLE_TEST_B', []);
        $b->addParentRole($a);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/role/' . $a->getId() . '/edit');
        $form = $crawler->selectButton('Wijzigingen opslaan')->form();
        foreach ($form['role[parentRoles]'] as $checkbox) {
            if ($checkbox->availableOptionValues() === [(string) $b->getId()]) {
                $checkbox->tick();
            }
        }
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'zou een lus maken');
    }

    public function testRuleForUnknownRouteIsRejected(): void
    {
        $this->login($this->createUser(['ROLE_ADMIN']));
        $permission = $this->permission('blog.edit');

        $crawler = $this->client->request('GET', '/admin/route-permission/new');
        $this->client->submit($crawler->selectButton('Regel aanmaken')->form([
            'route_permission[routePattern]' => 'admin_blgo_*',
            'route_permission[permission]' => (string) $permission->getId(),
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'past bij geen enkele beheerroute');
    }

    public function testAdminCantRemoveOwnAdminRole(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $this->login($admin);

        $crawler = $this->client->request('GET', '/admin/user/' . $admin->getId() . '/edit');
        $form = $crawler->selectButton('Wijzigingen opslaan')->form();
        foreach ($form['user[doctrineRoles]'] as $checkbox) {
            $checkbox->untick();
        }
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'ROLE_ADMIN niet van je eigen account weghalen');
    }

    /** @param list<Role|string> $roles */
    private function createUser(array $roles): User
    {
        $user = new User();
        $user->setEmail(uniqid('access-test-') . '@example.com');
        $user->setFullName('Access Test');
        $user->setPassword('not-used');
        foreach ($roles as $role) {
            $user->addRole($role instanceof Role ? $role : $this->createRole($role, []));
        }
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /** @param list<string> $permissions */
    private function createRole(string $name, array $permissions): Role
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => $name]) ?? (new Role())->setName($name);
        $this->em->persist($role);
        foreach ($permissions as $permission) {
            $role->addPermission($this->permission($permission));
        }
        $this->em->flush();

        return $role;
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

    /** @param list<string>|null $methods */
    private function createRule(string $pattern, string $permission, ?array $methods = null): RoutePermission
    {
        $rule = (new RoutePermission())
            ->setRoutePattern($pattern)
            ->setPermission($this->permission($permission))
            ->setMethods($methods);
        $this->em->persist($rule);
        $this->em->flush();

        return $rule;
    }

    private function login(User $user): void
    {
        $this->client->loginUser($user, 'primary_auth');
    }
}
