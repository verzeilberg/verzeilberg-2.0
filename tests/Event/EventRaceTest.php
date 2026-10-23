<?php

declare(strict_types=1);

namespace App\Tests\Event;

use App\Entity\Event;
use App\Entity\Strava\Activity;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * An event page shows my result: the Strava activity marked as a race on the event's days.
 * The dates are far in the past, so other test data doesn't interfere.
 */
class EventRaceTest extends WebTestCase
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

    public function testShowsTheLongestRaceOfTheDayWithItsRoute(): void
    {
        $event = $this->event('City Run', '1990-06-16');
        $this->activity('Warm-up with the kids', '1990-06-16 09:00', 5000, 1);
        $this->activity('City Run 15 km', '1990-06-16 11:00', 15140, 1, polyline: '_p~iF~ps|U_ulLnnqC_mqNvxq`@');
        $this->activity('Training run', '1990-06-16 19:00', 20000, 3);
        $this->activity('Race the day after', '1990-06-17 10:00', 21100, 1);

        $this->client->request('GET', '/event/' . $event->getSlug());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('aside', 'Mijn resultaat');
        $this->assertSelectorTextContains('aside', '15,14 km');
        $this->assertSelectorExists('a[href="https://www.strava.com/activities/act-City Run 15 km"]');
        $route = json_decode((string) $this->client->getCrawler()->filter('script[data-route]')->attr('data-route'), true);
        $this->assertSame(['lat' => 38.5, 'lng' => -120.2], $route[0]);
    }

    public function testNoResultWithoutARace(): void
    {
        $event = $this->event('Trail', '1990-09-01');
        $this->activity('Trail run', '1990-09-01 10:00', 12000, 3);

        $this->client->request('GET', '/event/' . $event->getSlug());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextNotContains('main', 'Mijn resultaat', 'a training run (maybe from home) is not shown');
    }

    private function event(string $title, string $date): Event
    {
        $event = (new Event())->setTitle($title . ' ' . uniqid())->setEventStartDate(new \DateTimeImmutable($date))->setEventEndDate(new \DateTimeImmutable($date));
        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }

    private function activity(string $name, string $start, float $meters, int $workoutType, ?string $polyline = null): void
    {
        $activity = (new Activity())
            ->setActivityId('act-' . $name)
            ->setAthleteId(1)
            ->setName($name)
            ->setDistance($meters)
            ->setMovingTime(4500)
            ->setElapsedTime(4600)
            ->setTotalElevationGain(12)
            ->setType('Run')
            ->setWorkoutType($workoutType)
            ->setStartDate(new \DateTimeImmutable($start))
            ->setStartDateLocal(new \DateTimeImmutable($start))
            ->setTimezone('Europe/Amsterdam')
            ->setSummaryPolyline($polyline);
        $this->em->persist($activity);
        $this->em->flush();
    }
}
