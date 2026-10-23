<?php

declare(strict_types=1);

namespace App\Tests\Event;

use App\Entity\Event;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * An event's URL is its title plus the year (/event/nobelhorst-run-2026), set once when it is first saved.
 * The test database may hold other events, so every title has its own random part.
 */
class EventSlugTest extends WebTestCase
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

    public function testSlugIsTitlePlusYearAndUnique(): void
    {
        $this->assertSame('nobelhorst-run-' . $this->term . '-2024', $this->event('Nobelhorst Run ' . $this->term, '2024-05-19')->getSlug());
        $this->assertSame('nobelhorst-run-' . $this->term . '-2026', $this->event('Nobelhorst Run ' . $this->term, '2026-05-17')->getSlug(), 'the same race another year');
        $this->assertSame('dam-tot-dam-loop-2026-' . $this->term, $this->event('Dam tot Dam loop 2026 ' . $this->term, '2026-09-27')->getSlug(), 'year already in the title');
        $this->assertSame('nobelhorst-run-' . $this->term . '-2026-2', $this->event('Nobelhorst Run ' . $this->term, '2026-11-01')->getSlug(), 'taken: a number is added');
    }

    public function testSlugStaysWhenTheTitleChanges(): void
    {
        $event = $this->event('Regatta ' . $this->term, '2026-06-01');
        $event->setTitle('Renamed ' . $this->term);
        $this->em->flush();

        $this->assertSame('regatta-' . $this->term . '-2026', $event->getSlug());
    }

    public function testPageIsFoundBySlug(): void
    {
        $event = $this->event('Weerwater Run ' . $this->term, '+10 days');

        $this->client->request('GET', '/event/' . $event->getSlug());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Weerwater Run ' . $this->term);

        $this->client->request('GET', '/event/no-such-event-' . $this->term);
        $this->assertResponseStatusCodeSame(404);
    }

    private function event(string $title, string $start): Event
    {
        $event = (new Event())
            ->setTitle($title)
            ->setEventStartDate(new \DateTimeImmutable($start))
            ->setEventEndDate(new \DateTimeImmutable($start));
        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }
}
