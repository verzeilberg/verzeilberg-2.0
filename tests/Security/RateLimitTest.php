<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Contact;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Wrong passwords, password reset mails and contact messages are limited per IP address
 * (see security.yaml and rate_limiter.yaml), so passwords can't be guessed and mailboxes not flooded.
 */
class RateLimitTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        // Every test its own address, so the limits of one test don't count for another
        $this->client->setServerParameter('REMOTE_ADDR', '10.2.' . random_int(0, 255) . '.' . random_int(1, 254));
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

    public function testLoginIsThrottledAfterFiveWrongPasswords(): void
    {
        $user = (new User())->setEmail(uniqid('throttle-') . '@example.com')->setFullName('Throttle Test');
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'Right-password1'));
        $this->em->persist($user);
        $this->em->flush();

        for ($i = 1; $i <= 5; ++$i) {
            $this->login($user->getEmail(), 'wrong-' . $i);
            $this->assertSelectorTextNotContains('.alert-danger', 'Te veel');
        }

        // Also the right password has to wait now
        $this->login($user->getEmail(), 'Right-password1');
        $this->assertSelectorTextContains('.alert-danger', 'Te veel');
    }

    public function testPasswordResetMailsAreLimited(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $crawler = $this->client->request('GET', '/forgot-password');
            $this->client->submit($crawler->selectButton('Stuur resetlink')->form(['email' => 'nobody-' . $i . '@example.com']));
            $this->assertSelectorTextContains('main', 'Als dit e-mailadres bij ons bekend is');
        }

        $crawler = $this->client->request('GET', '/forgot-password');
        $this->client->submit($crawler->selectButton('Stuur resetlink')->form(['email' => 'nobody@example.com']));
        $this->assertSelectorTextContains('main', 'Wacht een kwartier');
    }

    public function testPasswordResetNeedsTheFormToken(): void
    {
        $this->client->request('POST', '/forgot-password', ['email' => 'someone@example.com', '_csrf_token' => 'wrong']);

        $this->assertSelectorTextContains('main', 'Je sessie is verlopen');
        $this->assertEmailCount(0);
    }

    public function testContactMessagesAreLimited(): void
    {
        $before = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM contact');

        for ($i = 1; $i <= 6; ++$i) {
            $crawler = $this->client->request('GET', '/contact');
            $this->client->submit($crawler->filter('#contact_form')->form([
                'contact[salutation]' => Contact::SALUTATION_UND,
                'contact[name]' => 'Visitor',
                'contact[email]' => 'visitor@example.com',
                'contact[subject]' => 'Message ' . $i,
                'contact[message]' => 'Hello there, message number ' . $i,
            ]));
        }

        // 5 per hour: the 6th is refused
        $this->assertResponseStatusCodeSame(429);
        $this->assertSame($before + 5, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM contact'));
    }

    private function login(string $email, string $password): void
    {
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Inloggen')->form(['_username' => $email, '_password' => $password]));
        $this->client->followRedirect();
    }
}
