<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Role;
use App\Entity\User;
use App\Service\SiteStatus;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The admin status page: checks of the server, site and background jobs, the latest logged errors and a test mail.
 */
class StatusPageTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Connection $connection;
    private ?string $logFile = null;

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
        if ($this->logFile !== null) {
            @unlink($this->logFile);
        }
        parent::tearDown();
    }

    public function testShowsChecksAndLoggedErrors(): void
    {
        // A log file as production writes it: one JSON object per line
        $this->logFile = static::getContainer()->getParameter('kernel.logs_dir') . '/test-2099-01-01.log';
        file_put_contents($this->logFile, implode("\n", [
            json_encode(['message' => 'Just some info', 'level_name' => 'INFO', 'datetime' => '2099-01-01T10:00:00+00:00']),
            json_encode(['message' => 'Sending email failed: connection refused', 'level_name' => 'ERROR', 'datetime' => '2099-01-01T10:05:00+00:00']),
        ]) . "\n");
        $this->loginAsAdmin();

        $this->client->request('GET', '/admin/status');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main, body', 'Extensie sodium');
        $this->assertSelectorTextContains('body', 'Databasestructuur');
        $this->assertSelectorTextContains('body', 'Schrijfbaar public/uploads/');
        $this->assertSelectorTextContains('body', 'Onbevestigde registraties');
        $this->assertSelectorTextContains('table', 'Sending email failed: connection refused');
        $this->assertSelectorTextNotContains('table', 'Just some info');
    }

    public function testReadsOnlyTheEndOfALargeLog(): void
    {
        // A log much larger than what is read (1 MB): the newest error at the end is still found
        $this->logFile = static::getContainer()->getParameter('kernel.logs_dir') . '/test-2099-01-02.log';
        $filler = json_encode(['message' => str_repeat('x', 200), 'level_name' => 'INFO', 'datetime' => '2099-01-02T09:00:00+00:00']) . "\n";
        file_put_contents($this->logFile, str_repeat($filler, 15000) . json_encode(['message' => 'Newest failure', 'level_name' => 'ERROR', 'datetime' => '2099-01-02T10:00:00+00:00']) . "\n");
        $this->assertGreaterThan(3 * 1024 * 1024, filesize($this->logFile));

        $errors = static::getContainer()->get(SiteStatus::class)->recentErrors();

        $this->assertSame('Newest failure', $errors[0]['message']);
    }

    public function testSendsATestMailToTheAdmin(): void
    {
        $admin = $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/admin/status');

        $this->client->submit($crawler->filter('form[action="/admin/status/test-mail"]')->form());

        $this->assertEmailCount(1);
        $this->assertEmailAddressContains($this->getMailerMessage(), 'To', $admin->getEmail());
        $this->assertResponseRedirects('/admin/status');
    }

    public function testOnlyForAdmins(): void
    {
        $user = (new User())->setEmail(uniqid('status-') . '@example.com')->setFullName('Reader')->setPassword('not-used');
        $this->em->persist($user);
        $this->em->flush();
        $this->client->loginUser($user, 'primary_auth');

        $this->client->request('GET', '/admin/status');
        $this->assertResponseStatusCodeSame(403);
    }

    private function loginAsAdmin(): User
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_ADMIN']) ?? (new Role())->setName('ROLE_ADMIN')->setDescription('ROLE_ADMIN');
        $this->em->persist($role);
        $user = (new User())->setEmail(uniqid('status-') . '@example.com')->setFullName('Status test')->setPassword('not-used');
        $user->addRole($role);
        $this->em->persist($user);
        $this->em->flush();
        $this->client->loginUser($user, 'primary_auth');

        return $user;
    }
}
