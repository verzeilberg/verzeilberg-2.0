<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Doctrine\EncryptedStringType;
use App\Entity\EmailAccount;
use App\Entity\Strava\AccessToken;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Passwords and tokens the site uses itself are stored encrypted, reset links only as a hash:
 * a database dump gives no access to the mailboxes, Strava or anyone's account.
 */
class StoredSecretsTest extends WebTestCase
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

    public function testEmailAccountPasswordsAreEncrypted(): void
    {
        $account = (new EmailAccount())
            ->setName('Test')
            ->setEmail('box@example.test')
            ->setImapHost('imap.example.test')
            ->setUsername('box@example.test')
            ->setPassword('imap-secret')
            ->setSmtpPassword('smtp-secret');
        $this->em->persist($account);
        $this->em->flush();

        $row = $this->connection->fetchAssociative('SELECT password, smtp_password FROM email_account WHERE id = ?', [$account->getId()]);
        $this->assertStringNotContainsString('imap-secret', $row['password']);
        $this->assertStringNotContainsString('smtp-secret', $row['smtp_password']);

        $this->em->clear();
        $loaded = $this->em->find(EmailAccount::class, $account->getId());
        $this->assertSame('imap-secret', $loaded->getPassword());
        $this->assertSame('smtp-secret', $loaded->getSmtpPassword());
    }

    public function testStravaTokensAreEncryptedAndUnreadableWithAnotherSecret(): void
    {
        $token = (new AccessToken())->setExpiresAt(new \DateTimeImmutable('+1 hour'))->setRefreshToken('refresh-abc')->setAccessToken('access-abc');
        $this->em->persist($token);
        $this->em->flush();

        $row = $this->connection->fetchAssociative('SELECT refresh_token, access_token FROM strava_access_token WHERE id = ?', [$token->getId()]);
        $this->assertStringNotContainsString('refresh-abc', $row['refresh_token']);
        $this->assertStringNotContainsString('access-abc', $row['access_token']);

        // A database copied to a server with another APP_SECRET, or a value stored before encryption: empty, not an error
        $this->connection->executeStatement('UPDATE strava_access_token SET access_token = ? WHERE id = ?', ['plain-old-token', $token->getId()]);
        $this->em->clear();
        $loaded = $this->em->find(AccessToken::class, $token->getId());
        $this->assertSame('refresh-abc', $loaded->getRefreshToken());
        $this->assertSame('', $loaded->getAccessToken());
        $this->assertNull(EncryptedStringType::decrypt('plain-old-token'));
    }

    public function testResetLinkWorksButOnlyItsHashIsStored(): void
    {
        $user = (new User())->setEmail('reset.' . uniqid() . '@example.test')->setFullName('Reset Test')->setPassword('unused');
        $this->em->persist($user);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/forgot-password');
        $this->client->submit($crawler->selectButton('Stuur resetlink')->form(['email' => $user->getEmail()]));
        $this->assertEmailCount(1);
        preg_match('#/reset-password/([0-9a-f]{64})#', (string) $this->getMailerMessage()->getHtmlBody(), $match);
        $this->assertNotEmpty($match, 'the email holds the reset link');

        $stored = $this->connection->fetchOne('SELECT password_reset_token FROM user WHERE id = ?', [$user->getId()]);
        $this->assertSame(hash('sha256', $match[1]), $stored, 'only the hash is stored');

        $this->client->request('GET', '/reset-password/' . $match[1]);
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/reset-password/' . $stored);
        $this->assertResponseRedirects('/forgot-password', null, 'the stored hash is not a working link');
    }
}
