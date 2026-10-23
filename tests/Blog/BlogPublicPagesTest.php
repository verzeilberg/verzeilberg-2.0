<?php

declare(strict_types=1);

namespace App\Tests\Blog;

use App\Entity\BlogPost;
use App\Entity\Category;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The public blog pages: list, category, search (a grid like the events) and a post.
 * The test database may hold other posts, so every test works in its own category / with its own search term.
 */
class BlogPublicPagesTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Connection $connection;
    private Category $category;
    private string $term;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->em->getConnection();
        $this->connection->beginTransaction();

        $this->term = 'zq' . uniqid();
        $this->category = (new Category())->setName('Sailing ' . $this->term)->setSlug('sailing-' . $this->term)->setEnabled(true);
        $this->em->persist($this->category);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    public function testCategoryListShowsAllPostsOverPagesNotJustTen(): void
    {
        foreach (range(1, 14) as $i) {
            $this->post(sprintf('Post %02d', $i), sprintf('-%d days', 20 - $i));
        }

        $crawler = $this->client->request('GET', '/blog/category/' . $this->category->getSlug());
        $this->assertResponseIsSuccessful();
        $cards = $crawler->filter('.row-cols-xl-3 .card');
        $this->assertCount(12, $cards, 'twelve per page, in a grid like the events');
        $this->assertSame('Post 14', trim($cards->first()->filter('.card-title')->text()), 'newest first');
        $this->assertSelectorExists('.filter-panel .filter-heading:contains("Categorieën")');
        $this->assertSelectorExists('.filter-panel a[aria-current="page"]:contains("Sailing")');

        $crawler = $this->client->request('GET', '/blog/category/' . $this->category->getSlug() . '/page/2');
        $this->assertSame(['Post 02', 'Post 01'], $crawler->filter('.row-cols-xl-3 .card-title')->each(fn ($node) => trim($node->text())));
    }

    public function testSearchOnlyFindsPostsThatAreOnline(): void
    {
        $this->post('Visible ' . $this->term, '-1 day');
        $this->post('Scheduled ' . $this->term, '+3 days');
        $this->post('Expired ' . $this->term, '-10 days', '-1 day');
        $this->post('Draft ' . $this->term, '-1 day', null, BlogPost::STATUS_DRAFT);

        $crawler = $this->client->request('GET', '/blog/search?q=' . $this->term);

        $this->assertSame(['Visible ' . $this->term], $crawler->filter('.row-cols-xl-3 .card-title')->each(fn ($node) => trim($node->text())));
        $this->assertSelectorTextContains('h1', 'Zoeken: “' . $this->term . '”');
        $this->assertSame($this->term, $crawler->filter('#blogSearch')->attr('value'));
        $this->assertSame([], $this->client->request('GET', '/blog/search?q=' . $this->term . '%25')->filter('.row-cols-xl-3 .card')->each(fn ($node) => $node->text()), '% is literal');
    }

    public function testPostPageLikeAnEvent(): void
    {
        $older = $this->post('Older post ' . $this->term, '-5 days');
        $post = $this->post('The regatta ' . $this->term, '-1 day');

        $crawler = $this->client->request('GET', '/blog/' . $post->getSlug());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.page-head h1', $post->getTitle());
        $this->assertSelectorExists('.page-head a[href="/blog"]:contains("Alle berichten")');
        $this->assertSelectorTextContains('article.card .article-intro', 'Intro of the regatta');
        $this->assertSelectorExists('#comments .card-header:contains("0 reacties")');
        $latest = $crawler->filter('.col-lg-4 .card:contains("Nieuwste berichten")')->text();
        $this->assertStringContainsString($older->getTitle(), $latest);
        $this->assertStringNotContainsString($post->getTitle(), $latest, 'not the post itself');
    }

    private function post(string $title, string $online, ?string $offline = null, int $status = BlogPost::STATUS_ONLINE): BlogPost
    {
        $post = (new BlogPost())
            ->setTitle($title)
            ->setSlug(strtolower((string) preg_replace('/\W+/', '-', $title)) . '-' . uniqid())
            ->setIntroText('Intro of ' . strtolower($title))
            ->setContent('<p>Content of ' . $title . '</p>')
            ->setStatus($status)
            ->setDateOnline(new \DateTimeImmutable($online))
            ->setDateOffline($offline !== null ? new \DateTimeImmutable($offline) : null);
        $post->addCategory($this->category);
        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }
}
