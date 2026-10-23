<?php

declare(strict_types=1);

namespace App\Tests\User;

use App\Entity\Role;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\DataRetention;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;

/**
 * Someone who registers first confirms their email address; then the site's owner is mailed and an admin
 * approves or rejects the account. Until then the user can't log in. Registrations nobody handles are
 * deleted after 30 days; a honeypot and a rate limit keep bots out.
 */
class RegistrationApprovalTest extends WebTestCase
{
    private const PASSWORD = 'Secret-pass1!';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        // Every test its own address: the registrations of one test don't count against another's limit
        $this->client->setServerParameter('REMOTE_ADDR', '10.1.' . random_int(0, 255) . '.' . random_int(1, 254));
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

    public function testConfirmEmailThenWaitForApproval(): void
    {
        $email = $this->register();
        $this->assertResponseRedirects('/login');
        $user = $this->findUser($email);
        $this->assertTrue($user->isPending());
        $this->assertFalse($user->isEmailVerified());

        // Only the user is mailed, with the confirmation link
        $this->assertEmailCount(1);
        $mail = $this->getMailerMessage();
        $this->assertEmailAddressContains($mail, 'To', $email);
        $confirmLink = $this->linkIn($mail, '/register/confirm/');

        $this->login($email);
        $this->assertSelectorTextContains('.alert-danger', 'Bevestig eerst je e-mailadres');
        $this->assertSame($email, urldecode(explode('email=', $this->client->getCrawler()->filter('.alert-danger a')->attr('href'))[1]));

        // Confirming mails the site's owner
        $this->client->request('GET', $confirmLink);
        $this->assertResponseRedirects('/login');
        $this->assertEmailCount(1);
        $this->assertEmailAddressContains($this->getMailerMessage(), 'To', static::getContainer()->getParameter('app.notification_email'));
        $this->assertEmailHtmlBodyContains($this->getMailerMessage(), $email);
        $this->assertTrue($this->findUser($email)->isEmailVerified());

        $this->client->request('GET', $confirmLink);
        $this->client->followRedirect();
        $this->assertSelectorTextContains('main', 'ongeldig of al gebruikt');

        $this->login($email);
        $this->assertSelectorTextContains('.alert-danger', 'wacht nog op goedkeuring');
    }

    public function testResendConfirmationLink(): void
    {
        $email = $this->register();
        $firstLink = $this->linkIn($this->getMailerMessage(), '/register/confirm/');

        $crawler = $this->client->request('GET', '/register/resend?email=' . urlencode($email));
        $this->client->submit($crawler->selectButton('Stuur bevestigingslink')->form());
        $this->assertResponseRedirects('/login');
        $this->assertEmailCount(1);
        $newLink = $this->linkIn($this->getMailerMessage(), '/register/confirm/');
        $this->assertNotSame($firstLink, $newLink);

        // The old link no longer works, the new one does
        $this->client->request('GET', $firstLink);
        $this->assertFalse($this->findUser($email)->isEmailVerified());
        $this->client->request('GET', $newLink);
        $this->assertTrue($this->findUser($email)->isEmailVerified());

        // An unknown or confirmed address gets the same answer, but no mail
        $crawler = $this->client->request('GET', '/register/resend');
        $this->client->submit($crawler->selectButton('Stuur bevestigingslink')->form(['email' => $email]));
        $this->assertEmailCount(0);
        $this->client->followRedirect();
        $this->assertSelectorTextContains('main', 'Als er voor dit e-mailadres een account wacht');
    }

    public function testAdminApprovesAccount(): void
    {
        $admin = $this->admin();
        $pending = $this->pendingUser();
        $this->client->loginUser($admin, 'primary_auth');

        $crawler = $this->client->request('GET', '/admin/user/?status=pending');
        $this->assertSelectorTextSame('a[href="/admin/user/"] .badge', '1');
        $this->client->submit($crawler->filter('form[action="/admin/user/' . $pending->getId() . '/approve"]')->form());
        $this->assertResponseRedirects();

        $this->assertTrue($this->em->find(User::class, $pending->getId())->isActive());
        $this->assertEmailCount(1);
        $this->assertEmailAddressContains($this->getMailerMessage(), 'To', $pending->getEmail());
        $this->assertEmailHtmlBodyContains($this->getMailerMessage(), 'goedgekeurd');
    }

    public function testAdminRejectsAccount(): void
    {
        $admin = $this->admin();
        $pending = $this->pendingUser();
        $id = $pending->getId();
        $this->client->loginUser($admin, 'primary_auth');

        $crawler = $this->client->request('GET', '/admin/user/?status=pending');
        $this->client->submit($crawler->filter('form[action="/admin/user/' . $id . '/reject"]')->form());
        $this->assertResponseRedirects();

        $this->em->clear();
        $this->assertNull($this->em->find(User::class, $id));
        $this->assertEmailCount(1);
        $this->assertEmailAddressContains($this->getMailerMessage(), 'To', $pending->getEmail());
        $this->assertEmailHtmlBodyContains($this->getMailerMessage(), 'niet goedgekeurd');
    }

    public function testUnhandledRegistrationsAreDeletedAfter30Days(): void
    {
        $old = $this->pendingUser(created: '-31 days');
        $recent = $this->pendingUser(created: '-29 days');
        $active = $this->pendingUser(created: '-60 days')->setStatus(User::STATUS_ACTIVE);
        $this->em->flush();
        [$oldId, $recentId, $activeId] = [$old->getId(), $recent->getId(), $active->getId()];

        $deleted = static::getContainer()->get(DataRetention::class)->purge();

        $this->assertGreaterThanOrEqual(1, $deleted['registrations']);
        $this->em->clear();
        $this->assertNull($this->em->find(User::class, $oldId));
        $this->assertNotNull($this->em->find(User::class, $recentId));
        $this->assertNotNull($this->em->find(User::class, $activeId));
    }

    public function testHoneypotSavesNothing(): void
    {
        $email = $this->register(['registration_form[website]' => 'https://spam.example']);

        $this->assertResponseRedirects('/login');
        $this->assertNull(static::getContainer()->get(UserRepository::class)->findByEmail($email));
        $this->assertEmailCount(0);
    }

    public function testRegistrationsAreRateLimited(): void
    {
        for ($i = 1; $i <= 3; ++$i) {
            $this->register();
            $this->assertResponseRedirects('/login');
        }

        // 3 per hour per IP address: the 4th is refused
        $email = $this->register();
        $this->assertResponseStatusCodeSame(429);
        $this->assertNull(static::getContainer()->get(UserRepository::class)->findByEmail($email));
    }

    /** @param array<string, mixed> $extra form values on top of a valid registration */
    private function register(array $extra = []): string
    {
        $email = uniqid('register-') . '@example.com';
        $crawler = $this->client->request('GET', '/register');
        $this->client->submit($crawler->selectButton('Account aanmaken')->form($extra + [
            'registration_form[firstName]' => 'New',
            'registration_form[lastName]' => 'Member',
            'registration_form[email]' => $email,
            'registration_form[plainPassword][first]' => self::PASSWORD,
            'registration_form[plainPassword][second]' => self::PASSWORD,
            'registration_form[termsAccepted]' => true,
        ]));

        return $email;
    }

    private function login(string $email): void
    {
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Inloggen')->form(['_username' => $email, '_password' => self::PASSWORD]));
        $this->client->followRedirect();
    }

    private function linkIn(Email $mail, string $path): string
    {
        $this->assertSame(1, preg_match('#https?://[^/"]+(' . preg_quote($path, '#') . '[a-f0-9]+)#', (string) $mail->getHtmlBody(), $match));

        return $match[1];
    }

    private function findUser(string $email): User
    {
        $this->em->clear();

        return static::getContainer()->get(UserRepository::class)->findByEmail($email);
    }

    private function pendingUser(string $created = 'now'): User
    {
        $user = (new User())->setEmail(uniqid('pending-') . '@example.com')->setFullName('Pending Member')->setPassword('not-used')->setStatus(User::STATUS_PENDING);
        $user->markEmailVerified();
        $user->setDateCreated(new \DateTimeImmutable($created));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function admin(): User
    {
        // Only the registrations of this test wait for approval
        $this->connection->executeStatement('UPDATE user SET status = ? WHERE status = ?', [User::STATUS_RETIRED, User::STATUS_PENDING]);
        $this->em->clear();
        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_ADMIN']) ?? (new Role())->setName('ROLE_ADMIN')->setDescription('ROLE_ADMIN');
        $admin = (new User())->setEmail(uniqid('admin-') . '@example.com')->setFullName('Admin')->setPassword('not-used')->addRole($role);
        $this->em->persist($role);
        $this->em->persist($admin);
        $this->em->flush();

        return $admin;
    }
}
