<?php

declare(strict_types=1);

namespace App\Tests\User;

use App\Entity\BlogComment;
use App\Entity\BlogPost;
use App\Entity\Role;
use App\Entity\Setting;
use App\Entity\User;
use App\Repository\SettingRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;

/**
 * Users are mailed when someone replies to their comment (once the reply is visible), when they get
 * a friend request and when theirs is accepted; unless they switched that off on their profile.
 * Their page shows their recent comments.
 */
class ActivityNotificationTest extends WebTestCase
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

    public function testReplyIsMailedOnceItIsApproved(): void
    {
        static::getContainer()->get(SettingRepository::class)->setValue(Setting::COMMENT_MODERATION, '1');
        $anna = $this->user('Anna Reply');
        $parent = $this->comment($anna, 'First!');

        $this->client->loginUser($this->user('Bram Reply'), 'primary_auth');
        $this->reply($parent, 'Welcome Anna');
        $this->assertSame([], $this->mailsTo($anna), 'not while the reply waits for moderation');

        $reply = $this->em->getRepository(BlogComment::class)->findOneBy(['parent' => $parent->getId()]);
        $this->client->loginUser($this->user('Admin Reply', admin: true), 'primary_auth');
        $this->client->request('POST', '/admin/blog/comment/' . $reply->getId() . '/approve');
        $mails = $this->mailsTo($anna);
        $this->assertCount(1, $mails);
        $this->assertStringContainsString('Welcome Anna', $mails[0]->getHtmlBody());
        $this->assertStringContainsString('Bram Reply reageerde', $mails[0]->getSubject());

        $this->client->request('POST', '/admin/blog/comment/' . $reply->getId() . '/approve');
        $this->assertSame([], $this->mailsTo($anna), 'approving again mails nothing');
    }

    public function testNoMailForAReplyToYourselfOrWhenSwitchedOff(): void
    {
        static::getContainer()->get(SettingRepository::class)->setValue(Setting::COMMENT_MODERATION, '0');
        $anna = $this->user('Anna Self');
        $quiet = $this->user('Quiet Self');
        $quiet->setEmailNotifications(false);
        $this->em->flush();
        $annasComment = $this->comment($anna, 'Mine');
        $quietComment = $this->comment($quiet, 'Also mine');

        $this->client->loginUser($anna, 'primary_auth');
        $this->reply($annasComment, 'Replying to myself');
        $this->assertSame([], $this->mailsTo($anna));
        $this->reply($quietComment, 'Replying to the quiet one');
        $this->assertSame([], $this->mailsTo($quiet));
    }

    public function testFriendRequestAndAcceptAreMailed(): void
    {
        $anna = $this->user('Anna Friend', public: true);
        $bram = $this->user('Bram Friend');

        $this->client->loginUser($bram, 'primary_auth');
        $crawler = $this->client->request('GET', '/users/' . $anna->getId());
        $this->client->submit($crawler->selectButton('Vriend toevoegen')->form());
        $this->assertCount(1, $mails = $this->mailsTo($anna));
        $this->assertStringContainsString('Bram Friend wil je vriend worden', $mails[0]->getSubject());

        $this->client->loginUser($this->em->find(User::class, $anna->getId()), 'primary_auth');
        $crawler = $this->client->request('GET', '/friends');
        $this->client->submit($crawler->selectButton('Accepteren')->form());
        $this->assertCount(1, $mails = $this->mailsTo($bram));
        $this->assertStringContainsString('geaccepteerd', $mails[0]->getSubject());
    }

    public function testProfileSwitchesNotificationsOff(): void
    {
        $user = $this->user('Switch Off');
        $this->client->loginUser($user, 'primary_auth');

        $crawler = $this->client->request('GET', '/profile/edit');
        $form = $crawler->selectButton('Profiel opslaan')->form();
        $form['profile_form[emailNotifications]']->untick();
        $this->client->submit($form);

        $this->assertResponseRedirects('/profile');
        $this->em->clear();
        $this->assertFalse($this->em->find(User::class, $user->getId())->wantsEmailNotifications());
    }

    public function testUserPageShowsRecentComments(): void
    {
        $anna = $this->user('Anna Page', public: true);
        $this->comment($anna, 'A visible comment');
        $this->comment($anna, 'A comment waiting for approval', approved: false);

        $this->client->request('GET', '/users/' . $anna->getId());

        $this->assertSelectorTextContains('main', 'A visible comment');
        $this->assertSelectorTextNotContains('main', 'waiting for approval');
    }

    private function reply(BlogComment $parent, string $text): void
    {
        $crawler = $this->client->request('GET', '/blog/' . $parent->getBlogPost()->getSlug());
        $this->client->submit($crawler->filter('#reply-' . $parent->getId() . ' form')->form(['comment' => $text]));
        $this->assertResponseRedirects();
    }

    /** @return list<Email> the mails of the last request to this user */
    private function mailsTo(User $user): array
    {
        return array_values(array_filter(
            $this->getMailerMessages(),
            fn ($mail) => $mail instanceof Email && $mail->getTo()[0]->getAddress() === $user->getEmail()
        ));
    }

    private function comment(User $author, string $text, bool $approved = true): BlogComment
    {
        $post = (new BlogPost())->setTitle('Notify ' . $text)->setSlug('notify-' . uniqid())->setIntroText('Intro')->setContent('<p>Content</p>')
            ->setStatus(BlogPost::STATUS_ONLINE)->setDateOnline(new \DateTimeImmutable('-1 day'));
        $comment = (new BlogComment())->setContent($text)->setBlogPost($post)->setAuthor($author)->setApproved($approved);
        $this->em->persist($post);
        $this->em->persist($comment);
        $this->em->flush();
        // The post's comment list is loaded fresh on its page
        $this->em->refresh($post);

        return $comment;
    }

    private function user(string $name, bool $public = false, bool $admin = false): User
    {
        $user = (new User())->setEmail(uniqid('activity-') . '@example.com')->setFullName($name)->setPassword('not-used')->setProfilePublic($public);
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
