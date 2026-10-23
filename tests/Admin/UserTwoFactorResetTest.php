<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Role;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The user list shows how each user logs in; an admin can put a user who lost their phone back on codes by email.
 */
class UserTwoFactorResetTest extends WebTestCase
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

    public function testAdminResetsTheAuthenticatorApp(): void
    {
        $user = (new User())->setEmail(uniqid('totp-') . '@example.com')->setFullName('Lost Phone')->setPassword('not-used');
        $user->useAuthenticatorApp('JBSWY3DPEHPK3PXP', ['aaaa-bbbb']);
        $this->em->persist($user);
        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_ADMIN']) ?? (new Role())->setName('ROLE_ADMIN')->setDescription('ROLE_ADMIN');
        $admin = (new User())->setEmail(uniqid('admin-') . '@example.com')->setFullName('Admin')->setPassword('not-used')->addRole($role);
        $this->em->persist($role);
        $this->em->persist($admin);
        $this->em->flush();
        $trustedVersion = $user->getTrustedTokenVersion();
        $this->client->loginUser($admin, 'primary_auth');

        $this->client->request('GET', '/admin/user/?search=' . urlencode($user->getEmail()));
        $this->assertSelectorTextContains('tbody', 'App');

        $crawler = $this->client->request('GET', '/admin/user/' . $user->getId() . '/edit');
        $this->client->submit($crawler->selectButton('Terugzetten naar codes per e-mail')->form());
        $this->assertResponseRedirects('/admin/user/' . $user->getId() . '/edit');

        $this->em->clear();
        $user = $this->em->find(User::class, $user->getId());
        $this->assertFalse($user->isTotpAuthenticationEnabled());
        $this->assertSame(0, $user->getBackupCodeCount());
        $this->assertGreaterThan($trustedVersion, $user->getTrustedTokenVersion());
    }
}
