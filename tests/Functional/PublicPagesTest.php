<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\BlogPost;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** The about page, the sitemap and robots.txt */
class PublicPagesTest extends WebTestCase
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

    public function testAboutPageRefersToTheContactForm(): void
    {
        $crawler = $this->client->request('GET', '/about');
        self::assertResponseIsSuccessful();

        $paragraph = $crawler->filter('p')->reduce(fn ($p) => str_contains($p->text(), 'Neem gerust contact op'));
        $this->assertCount(1, $paragraph);
        $this->assertSame('Neem gerust contact op via het contactformulier.', trim($paragraph->text()));
        $this->assertSame('/contact', $paragraph->filter('a')->attr('href'));
    }

    public function testSitemapListsOnlyPostsThatAreOnline(): void
    {
        $online = $this->post('Online', '-1 day');
        $future = $this->post('Future', '+1 day');
        $expired = $this->post('Expired', '-10 days', '-1 day');
        $draft = $this->post('Draft', '-1 day', null, BlogPost::STATUS_DRAFT);

        $this->client->request('GET', '/sitemap.xml');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');

        $xml = new \SimpleXMLElement($this->client->getInternalResponse()->getContent());
        $locs = array_map(fn ($url) => (string) $url->loc, iterator_to_array($xml->url, false));

        $this->assertContains('http://localhost/about', $locs);
        $this->assertContains('http://localhost/blog/' . $online->getSlug(), $locs);
        foreach ([$future, $expired, $draft] as $post) {
            $this->assertNotContains('http://localhost/blog/' . $post->getSlug(), $locs, $post->getTitle());
        }
    }

    public function testRobotsTxtPointsToTheSitemap(): void
    {
        $this->client->request('GET', '/robots.txt');
        self::assertResponseIsSuccessful();

        $robots = $this->client->getInternalResponse()->getContent();
        $this->assertStringStartsWith("User-agent: *\n", $robots);
        $this->assertStringContainsString('Disallow: /admin', $robots);
        $this->assertStringContainsString('Sitemap: http://localhost/sitemap.xml', $robots);
    }

    private function post(string $title, string $online, ?string $offline = null, int $status = BlogPost::STATUS_ONLINE): BlogPost
    {
        $post = (new BlogPost())
            ->setTitle($title)
            ->setSlug(strtolower($title) . '-' . uniqid())
            ->setIntroText('Intro')
            ->setContent('<p>Content</p>')
            ->setStatus($status)
            ->setDateOnline(new \DateTimeImmutable($online))
            ->setDateOffline($offline !== null ? new \DateTimeImmutable($offline) : null);
        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }
}
