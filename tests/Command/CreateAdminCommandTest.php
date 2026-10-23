<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateAdminCommandTest extends KernelTestCase
{
    private Connection $connection;
    private CommandTester $command;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $this->connection->beginTransaction();
        $this->command = new CommandTester((new Application(self::$kernel))->find('app:user:create-admin'));
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        putenv('TEST_ADMIN_PASSWORD');
        parent::tearDown();
    }

    public function testCreatesTheRolesAndAnAdminThatCanLogIn(): void
    {
        // A new database has no roles
        $this->connection->executeStatement('DELETE FROM user_role');
        $this->connection->executeStatement('DELETE FROM role_hierarchy');
        $this->connection->executeStatement('DELETE FROM role_permission');
        $this->connection->executeStatement('DELETE FROM role');
        putenv('TEST_ADMIN_PASSWORD=correct horse battery');
        $email = uniqid('admin-') . '@example.com';

        $this->command->execute(['--email' => $email, '--name' => 'Jan Jansen', '--password-from-env' => 'TEST_ADMIN_PASSWORD']);

        $this->command->assertCommandIsSuccessful();
        $this->assertStringContainsString('Created the roles: ROLE_ADMIN, ROLE_EDITOR, ROLE_AUTHOR, ROLE_USER', $this->command->getDisplay());
        $user = $this->user($email);
        $this->assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
        $this->assertSame(['Jan', 'Jansen'], [$user->getDetails()?->getFirstName(), $user->getDetails()?->getLastName()]);
        $this->assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'correct horse battery'));
    }

    public function testExistingAccountGetsAdminRightsAndANewPassword(): void
    {
        putenv('TEST_ADMIN_PASSWORD=first password');
        $email = uniqid('admin-') . '@example.com';
        $this->command->execute(['--email' => $email, '--name' => 'Jan', '--password-from-env' => 'TEST_ADMIN_PASSWORD']);

        putenv('TEST_ADMIN_PASSWORD=second password');
        $this->command->setInputs(['yes']);
        $this->command->execute(['--email' => $email, '--password-from-env' => 'TEST_ADMIN_PASSWORD']);

        $this->command->assertCommandIsSuccessful();
        $this->assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($this->user($email), 'second password'));
        $this->assertSame(['ROLE_ADMIN', 'ROLE_USER'], $this->user($email)->getRoles(), 'no role twice');
    }

    public function testRefusesAShortPasswordAndABadEmail(): void
    {
        putenv('TEST_ADMIN_PASSWORD=short');
        $this->assertSame(2, $this->command->execute(['--email' => uniqid() . '@example.com', '--name' => 'Jan', '--password-from-env' => 'TEST_ADMIN_PASSWORD']));
        $this->assertStringContainsString('at least 8 characters', $this->command->getDisplay());

        $this->assertSame(2, $this->command->execute(['--email' => 'not-an-email', '--name' => 'Jan', '--password-from-env' => 'TEST_ADMIN_PASSWORD']));
    }

    private function user(string $email): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($user);

        return $user;
    }
}
