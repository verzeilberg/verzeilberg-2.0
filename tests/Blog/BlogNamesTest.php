<?php

declare(strict_types=1);

namespace App\Tests\Blog;

use App\Entity\BlogPost;
use App\Entity\User;
use App\Service\NameMasker;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Names in a post are written as [[Jan de Vries]]: logged in visitors see the name, everyone else the initials,
 * and the full name must not be in anything a visitor who isn't logged in can get: page, meta tags, feed, search.
 */
class BlogNamesTest extends WebTestCase
{
    private const SURNAME = 'Zwanenburg';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Connection $connection;
    private BlogPost $post;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->em->getConnection();
        $this->connection->beginTransaction();

        $this->post = (new BlogPost())->setTitle('Duurloop met vrienden')->setSlug('names-test-' . uniqid())
            ->setIntroText('Samen met [[Pieter ' . self::SURNAME . ']] gelopen')
            ->setContent('<div>Vandaag liep ik met [[Anne-Marie de <strong>' . self::SURNAME . '</strong>]] en [[Pieter ' . self::SURNAME . ']].</div>')
            ->setStatus(BlogPost::STATUS_ONLINE)->setDateOnline(new \DateTimeImmutable('-1 minute'));
        $this->em->persist($this->post);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    public function testInitials(): void
    {
        $this->assertSame('J.d.V.', NameMasker::initials('Jan de Vries'));
        $this->assertSame('A.M.d.Z.', NameMasker::initials('Anne-Marie de <strong>Zwanenburg</strong>'));
        $this->assertSame('É.', NameMasker::initials(' Élise '));
        $this->assertSame('Hoi J.J.!', NameMasker::mask('Hoi [[Jan Jansen]]!'));
        $this->assertSame('Hoi Jan Jansen!', NameMasker::reveal('Hoi [[Jan Jansen]]!'));
    }

    public function testNamesWithTheSameInitialsGetASecondLetter(): void
    {
        // Never more than two letters, and only when that tells names apart
        $this->assertSame(['karel' => 'K.', 'karin' => 'K.'], NameMasker::abbreviations(['Karel', 'Karin']));
        $this->assertSame(['karel jansen' => 'K.J.', 'karin pieters' => 'K.P.'], NameMasker::abbreviations(['Karel Jansen', 'Karin Pieters']));
        $this->assertSame(['karel' => 'Ka.', 'kees' => 'Ke.'], NameMasker::abbreviations(['Karel', 'Kees']));
        $this->assertSame(['karel' => 'Ka.', 'kees' => 'Ke.', 'karin' => 'Ka.', 'piet' => 'P.'], NameMasker::abbreviations(['Karel', 'Kees', 'Karin', 'Piet']));
        $this->assertSame(['jan jansen' => 'Ja.J.', 'joop jansen' => 'Jo.J.'], NameMasker::abbreviations(['Jan Jansen', 'Joop Jansen']));
        $this->assertSame(['jan jansen' => 'J.J.', 'jan jacobs' => 'J.J.'], NameMasker::abbreviations(['Jan Jansen', 'Jan Jacobs']));
        $this->assertSame(['jo' => 'J.', 'joop' => 'J.'], NameMasker::abbreviations(['Jo', 'Joop']));
        $this->assertSame(['jan jansen' => 'Ja.J.', 'jan jacobs' => 'Ja.J.', 'joop jansen' => 'Jo.J.'], NameMasker::abbreviations(['Jan Jansen', 'Jan Jacobs', 'Joop Jansen']));
        $this->assertSame(['jan' => 'J.', 'jan jansen' => 'J.J.'], NameMasker::abbreviations(['Jan', 'Jan Jansen']));
        // The same name twice, written a bit differently, is one person
        $this->assertSame(['karel' => 'K.'], NameMasker::abbreviations(['Karel', '<strong>karel</strong>']));

        // The whole post decides: the intro alone has only Karel, but the content also has Kees
        $context = 'Met [[Karel]]' . "\n" . '<div>[[Kees]] en [[Karel]]</div>';
        $this->assertSame('Met Ka.', NameMasker::mask('Met [[Karel]]', $context));
        $this->assertSame('Met K.', NameMasker::mask('Met [[Karel]]'));
    }

    public function testPageKeepsKarelAndKeesApart(): void
    {
        $this->post->setIntroText('Lopen met [[Karel]]')->setContent('<div>[[Karel]] en [[Kees]] liepen mee.</div>');
        $this->em->flush();

        $this->client->request('GET', '/blog/' . $this->post->getSlug());

        $this->assertSelectorTextContains('.article-intro', 'Lopen met Ka.');
        $this->assertSelectorTextContains('.prose.content', 'Ka. en Ke. liepen mee.');
        $this->assertStringNotContainsString('Karel', $this->client->getResponse()->getContent());
        $this->assertStringNotContainsString('Kees', $this->client->getResponse()->getContent());
    }

    public function testVisitorSeesOnlyInitials(): void
    {
        $this->client->request('GET', '/blog/' . $this->post->getSlug());

        $this->assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString(self::SURNAME, $html);
        $this->assertStringNotContainsString('[[', $html);
        $this->assertSelectorTextContains('.article-intro', 'Samen met P.Z. gelopen');
        $this->assertSelectorTextContains('.prose.content', 'Vandaag liep ik met A.M.d.Z. en P.Z..');
    }

    public function testLoggedInUserSeesNames(): void
    {
        $this->client->loginUser($this->user(), 'primary_auth');
        $this->client->request('GET', '/blog/' . $this->post->getSlug());

        $this->assertSelectorTextContains('.article-intro', 'Samen met Pieter ' . self::SURNAME . ' gelopen');
        $this->assertSelectorTextContains('.prose.content', 'Anne-Marie de ' . self::SURNAME);
        $this->assertStringNotContainsString('[[', $this->client->getResponse()->getContent());
    }

    public function testListsAndFeedShowOnlyInitials(): void
    {
        foreach (['/', '/blog', '/blog/feed.xml'] as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseIsSuccessful($url);
            $this->assertStringNotContainsString(self::SURNAME, $this->client->getResponse()->getContent(), $url);
        }

        // The feed is public and cached: initials also for logged in users
        $this->client->loginUser($this->user(), 'primary_auth');
        $this->client->request('GET', '/blog/feed.xml');
        $this->assertStringNotContainsString(self::SURNAME, $this->client->getResponse()->getContent());
    }

    public function testNamesCanNotBeFoundBySearchingWithoutLogin(): void
    {
        foreach (['/blog/search?q=', '/search?q='] as $url) {
            $this->client->request('GET', $url . self::SURNAME);
            $this->assertResponseIsSuccessful($url);
            $this->assertStringNotContainsString($this->post->getSlug(), $this->client->getResponse()->getContent(), $url . ': found by the name');

            // Other words of the post still find it
            $this->client->request('GET', $url . 'Vandaag liep');
            $this->assertStringContainsString($this->post->getSlug(), $this->client->getResponse()->getContent(), $url . ': found by its text');
        }

        $this->client->loginUser($this->user(), 'primary_auth');
        foreach (['/blog/search?q=', '/search?q='] as $url) {
            $this->client->request('GET', $url . self::SURNAME);
            $this->assertStringContainsString($this->post->getSlug(), $this->client->getResponse()->getContent(), $url . ': logged in finds the name');
        }
    }

    private function user(): User
    {
        $user = (new User())->setEmail(uniqid('names-') . '@example.com')->setFullName('Reader')->setPassword('not-used');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
