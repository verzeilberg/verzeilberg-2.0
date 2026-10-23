<?php

declare(strict_types=1);

namespace App\Tests\Running;

use App\Entity\Event;
use App\Entity\Strava\Activity;
use App\Service\Strava\RunningStats;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The public running overview. The runs are in 1990, so other test data doesn't count along.
 */
class RunningPageTest extends WebTestCase
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

    public function testYearTotalsRacesAndPage(): void
    {
        $event = (new Event())->setTitle('City Run ' . uniqid())->setEventStartDate(new \DateTimeImmutable('1990-06-16'))->setEventEndDate(new \DateTimeImmutable('1990-06-16'));
        $this->em->persist($event);
        $this->activity('Morning run', '1990-01-10 08:00', 8000, 2700, null);
        $this->activity('Race name from Strava', '1990-06-16 11:00', 10050, 2682, 1);
        $this->activity('Ride', '1990-06-20 11:00', 40000, 5000, null, 'Ride');
        $this->em->flush();

        $stats = static::getContainer()->get(RunningStats::class);
        $year = $stats->year(1990);
        $this->assertSame(2, $year['runs'], 'only runs, no rides');
        $this->assertSame(18.1, $year['km']);
        $this->assertSame(1, $year['races']);
        $this->assertSame(8.0, $year['kmPerMonth'][0]);
        $this->assertSame(10.1, $year['kmPerMonth'][5]);

        $races = $stats->races(1990);
        $this->assertCount(1, $races);
        $this->assertSame($event->getTitle(), $races[0]['name'], 'the event title, when there is one that day');

        $this->client->request('GET', '/running?year=1990');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.stat-tile', '18');
        $this->assertSelectorExists('a[href="/event/' . $event->getSlug() . '"]');
        $this->assertSelectorTextContains('body', '44:42', 'the race time');
        $this->assertSelectorTextNotContains('body', 'Morning run', 'no names of training runs');
    }

    public function testFeedsForSearchEnginesAndCalendars(): void
    {
        $event = (new Event())->setTitle('Feed race ' . uniqid())->setEventStartDate(new \DateTimeImmutable('+30 days'))->setEventEndDate(new \DateTimeImmutable('+30 days'))
            ->setEventStartTime(new \DateTimeImmutable('10:30'));
        $this->em->persist($event);
        $this->em->flush();

        $this->client->request('GET', '/sitemap.xml');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('/event/' . $event->getSlug() . '</loc>', (string) $this->client->getResponse()->getContent());
        $this->assertStringContainsString('/running</loc>', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/robots.txt');
        $this->assertStringContainsString('Disallow: /admin/', (string) $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Sitemap: http://localhost/sitemap.xml', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/blog/feed.xml');
        $this->assertResponseHeaderSame('Content-Type', 'application/rss+xml; charset=UTF-8');
        $this->assertNotFalse(simplexml_load_string((string) $this->client->getResponse()->getContent()));

        $this->client->request('GET', '/event/' . $event->getSlug() . '.ics');
        $ics = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('SUMMARY:' . $event->getTitle(), $ics);
        $this->assertMatchesRegularExpression('/DTSTART:\d{8}T(08|09)3000Z/', $ics, '10:30 Amsterdam time in UTC');

        $this->client->request('GET', '/events/feed.ics');
        $this->assertStringContainsString('X-WR-CALNAME:Verzeilberg.nl events', (string) $this->client->getResponse()->getContent());
    }

    private function activity(string $name, string $start, float $meters, int $seconds, ?int $workoutType, string $type = 'Run'): void
    {
        $this->em->persist((new Activity())
            ->setActivityId(uniqid('act-'))
            ->setAthleteId(1)
            ->setName($name)
            ->setDistance($meters)
            ->setMovingTime($seconds)
            ->setElapsedTime($seconds)
            ->setTotalElevationGain(10)
            ->setType($type)
            ->setWorkoutType($workoutType)
            ->setStartDate(new \DateTimeImmutable($start))
            ->setStartDateLocal(new \DateTimeImmutable($start))
            ->setTimezone('Europe/Amsterdam'));
    }
}
