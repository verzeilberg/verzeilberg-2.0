<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\BlogComment;
use App\Entity\BlogPost;
use App\Entity\Contact;
use App\Entity\Role;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A new comment is mailed to the site's owner; the admin menu counts comments waiting for approval
 * and unread contact messages.
 */
class AdminNotificationTest extends WebTestCase
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
        // Start from a known state: other tests' data isn't counted
        $this->connection->executeStatement('UPDATE blog_comment SET is_approved = 1');
        $this->connection->executeStatement('UPDATE contact SET read_at = NOW()');
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    public function testNewCommentIsMailed(): void
    {
        $post = (new BlogPost())->setTitle('Notify test')->setSlug('notify-test-' . uniqid())->setIntroText('Intro')->setContent('<p>Content</p>')
            ->setStatus(BlogPost::STATUS_ONLINE)->setDateOnline(new \DateTimeImmutable('-1 day'));
        $this->em->persist($post);
        $this->client->loginUser($this->user('Reader'), 'primary_auth');

        $crawler = $this->client->request('GET', '/blog/' . $post->getSlug());
        $this->client->submit($crawler->filter('#comment-form')->form(['comment' => 'Nice race!']));

        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailAddressContains($email, 'To', static::getContainer()->getParameter('app.notification_email'));
        $this->assertEmailHtmlBodyContains($email, 'Nice race!');
        $this->assertEmailHtmlBodyContains($email, 'Notify test');
    }

    public function testAdminMenuCountsWhatWaits(): void
    {
        $post = (new BlogPost())->setTitle('Count test')->setSlug('count-test-' . uniqid())->setIntroText('Intro')->setContent('<p>Content</p>')
            ->setStatus(BlogPost::STATUS_ONLINE);
        $this->em->persist($post);
        foreach ([false, false, true] as $approved) {
            $this->em->persist((new BlogComment())->setContent('Comment')->setBlogPost($post)->setApproved($approved));
        }
        $contact = (new Contact())->setName('Visitor')->setEmail('visitor@example.test')->setSubject('Hello')->setMessage('A message');
        $this->em->persist($contact);
        $this->client->loginUser($this->user('Admin', admin: true), 'primary_auth');

        $this->client->request('GET', '/admin/');
        $this->assertSelectorTextSame('a[href="/admin/blog/comments"] .badge', '2');
        $this->assertSelectorTextSame('a[href="/admin/contact/"] .badge', '1');

        $this->client->request('GET', '/admin/contact/' . $contact->getId());
        $this->assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/');
        $this->assertSelectorNotExists('a[href="/admin/contact/"] .badge', 'read once it was opened');
    }

    private function user(string $name, bool $admin = false): User
    {
        $user = (new User())->setEmail(uniqid('notify-') . '@example.com')->setFullName($name)->setPassword('not-used');
        if ($admin) {
            $role = $this->em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_ADMIN']) ?? (new Role())->setName('ROLE_ADMIN')->setDescription('ROLE_ADMIN');
            $this->em->persist($role);
            $user->addRole($role);
        }
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
