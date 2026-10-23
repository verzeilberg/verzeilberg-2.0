<?php

declare(strict_types=1);

namespace App\Tests\Privacy;

use App\Entity\Contact;
use App\Entity\EmailAccount;
use App\Entity\EmailMessage;
use App\Service\DataRetention;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Contact messages and local copies of emails (with attachment files) are deleted after six months.
 */
class DataRetentionTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Connection $connection;
    private string $attachments;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->em->getConnection();
        $this->connection->beginTransaction();
        $this->attachments = sys_get_temp_dir() . '/retention-test-' . uniqid();
        mkdir($this->attachments);
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        array_map('unlink', glob($this->attachments . '/*') ?: []);
        rmdir($this->attachments);
        parent::tearDown();
    }

    public function testOnlyDataOlderThanSixMonthsIsDeleted(): void
    {
        $oldContact = $this->contact('-7 months');
        $newContact = $this->contact('-5 months');

        $account = (new EmailAccount())->setName('Test')->setEmail('box@example.test')->setImapHost('imap.example.test')->setUsername('box')->setPassword('secret');
        $this->em->persist($account);
        $oldMail = $this->email($account, received: '-8 months');
        $newMail = $this->email($account, received: '-1 month');
        $oldSentMail = $this->email($account, received: null, sent: '-7 months');
        $fetchedLongAgo = $this->email($account, received: null, sent: null, fetched: '-7 months');
        $this->em->flush();
        $ids = fn (array $entities) => array_map(fn ($entity) => $entity->getId(), $entities);
        [$oldContactId, $newContactId] = $ids([$oldContact, $newContact]);
        [$oldMailId, $newMailId, $oldSentMailId, $fetchedId] = $ids([$oldMail, $newMail, $oldSentMail, $fetchedLongAgo]);

        touch($oldFile = $this->attachments . '/12_old.pdf', strtotime('-7 months'));
        touch($newFile = $this->attachments . '/13_new.pdf', strtotime('-1 week'));

        $deleted = (new DataRetention($this->em, $this->attachments . '/*'))->purge();

        $this->assertGreaterThanOrEqual(1, $deleted['contacts']);
        $this->assertGreaterThanOrEqual(3, $deleted['emails']);
        $this->assertSame(1, $deleted['attachments']);
        $this->em->clear();
        $this->assertNull($this->em->find(Contact::class, $oldContactId));
        $this->assertNotNull($this->em->find(Contact::class, $newContactId));
        $this->assertNull($this->em->find(EmailMessage::class, $oldMailId));
        $this->assertNull($this->em->find(EmailMessage::class, $oldSentMailId), 'no received date: the sent date counts');
        $this->assertNull($this->em->find(EmailMessage::class, $fetchedId), 'no dates at all: when it was fetched counts');
        $this->assertNotNull($this->em->find(EmailMessage::class, $newMailId));
        $this->assertFileDoesNotExist($oldFile);
        $this->assertFileExists($newFile);
    }

    private function contact(string $created): Contact
    {
        $contact = (new Contact())->setName('Visitor')->setEmail('visitor@example.test')->setSubject('Hello')->setMessage('A message');
        $contact->setDateCreated(new \DateTimeImmutable($created));
        $this->em->persist($contact);
        $this->em->flush();

        return $contact;
    }

    private function email(EmailAccount $account, ?string $received, ?string $sent = null, string $fetched = 'now'): EmailMessage
    {
        $message = (new EmailMessage())
            ->setAccount($account)
            ->setFolder('INBOX')
            ->setUid(uniqid())
            ->setReceivedAt($received !== null ? new \DateTimeImmutable($received) : null)
            ->setSentAt($sent !== null ? new \DateTimeImmutable($sent) : null);
        $message->setDateCreated(new \DateTimeImmutable($fetched));
        $this->em->persist($message);

        return $message;
    }
}
