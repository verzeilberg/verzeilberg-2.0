<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Event;
use App\Repository\EventRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Gives a new event its URL slug: the title plus the year when the title doesn't contain it, so a race held
 * every year gets its own URL ("Nobelhorst Run" on 2026-05-17 becomes "nobelhorst-run-2026"). A slug that is
 * taken gets "-2", "-3", … An existing event keeps its slug, so its URL doesn't change when the title is edited.
 * Migration Version20261022100000 gave the existing events their slugs by the same rule.
 */
#[AsEntityListener(event: Events::prePersist, entity: Event::class)]
class EventSlugListener
{
    /** @var array<string, true> slugs handed out in this request but not saved yet (several new events in one flush) */
    private array $pending = [];

    public function __construct(
        private SluggerInterface $slugger,
        private EventRepository $eventRepository,
    ) {
    }

    public function prePersist(Event $event): void
    {
        if ($event->getSlug() !== null) {
            return;
        }

        $year = $event->getEventStartDate()->format('Y');
        $base = (string) $this->slugger->slug($event->getTitle())->lower();
        if (!str_contains($base, $year)) {
            $base = ltrim($base . '-' . $year, '-');
        }

        $slug = $base;
        for ($i = 2; isset($this->pending[$slug]) || $this->eventRepository->count(['slug' => $slug]) > 0; ++$i) {
            $slug = $base . '-' . $i;
        }

        $this->pending[$slug] = true;
        $event->setSlug($slug);
    }
}
