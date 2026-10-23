<?php

declare(strict_types=1);

namespace App\Tests\Search;

use App\Entity\BlogPost;
use App\Entity\Steam\SteamGame;
use App\Entity\Steam\SteamGameReview;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The site search (top bar): blog posts, events and games.
 */
class SiteSearchTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Connection $connection;
    private string $term;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->em->getConnection();
        $this->connection->beginTransaction();
        $this->term = 'zq' . uniqid();
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    public function testFindsGamesByNameAndByPublishedReview(): void
    {
        $this->game(900001, 'Factorio ' . $this->term);
        $this->game(900002, 'Portal 2', 'Clever puzzles, ' . $this->term . ' humour.', 5);
        $this->game(900003, 'Among Us', 'Draft about ' . $this->term, 2, published: false);
        $this->game(900004, 'Secret ' . $this->term, hidden: true);

        $crawler = $this->client->request('GET', '/search?q=' . $this->term);
        $this->assertResponseIsSuccessful();

        $games = $crawler->filter('article:contains("Game")');
        $this->assertSame(['Portal 2', 'Factorio ' . $this->term], $games->filter('h2 a')->each(fn ($node) => trim($node->text())), 'name or published review; not a draft or a hidden game');
        $this->assertSame('/games/900002', $games->first()->filter('h2 a')->attr('href'));
        $this->assertStringContainsString('Mijn review: Clever puzzles', $games->first()->text());
        $this->assertSame('5 van 5 (Uitstekend)', $games->first()->filter('.steam-stars')->attr('aria-label'));
        $this->assertSelectorExists('a[href="/games"]:contains("Alle games")');
    }

    public function testOnlyBlogPostsThatAreOnline(): void
    {
        foreach (['Visible' => ['-1 day', null], 'Scheduled' => ['+3 days', null], 'Expired' => ['-10 days', '-1 day']] as $name => [$online, $offline]) {
            $post = (new BlogPost())->setTitle($name . ' ' . $this->term)->setSlug(strtolower($name) . '-' . $this->term)
                ->setIntroText('Intro')->setContent('Content')->setStatus(BlogPost::STATUS_ONLINE)
                ->setDateOnline(new \DateTimeImmutable($online))->setDateOffline($offline ? new \DateTimeImmutable($offline) : null);
            $this->em->persist($post);
        }
        $this->em->flush();

        $crawler = $this->client->request('GET', '/search?q=' . $this->term);

        $this->assertSame(['Visible ' . $this->term], $crawler->filter('article:contains("Blog") h2 a')->each(fn ($node) => trim($node->text())));
    }

    private function game(int $appId, string $name, ?string $review = null, int $rating = 4, bool $published = true, bool $hidden = false): SteamGame
    {
        $game = (new SteamGame($appId))->setName($name)->setPlaytimeForever(60)->setHidden($hidden);
        $this->em->persist($game);
        if ($review !== null) {
            $this->em->persist((new SteamGameReview($game))->setRating($rating)->setBody($review)->setPublished($published));
        }
        $this->em->flush();

        return $game;
    }
}
