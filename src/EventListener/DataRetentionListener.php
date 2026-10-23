<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\DataRetention;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Runs DataRetention once a day, after a page has been sent to a visitor, so no cronjob is needed
 * (the server has none). A failure is logged and doesn't affect the site.
 */
#[AsEventListener(event: KernelEvents::TERMINATE)]
class DataRetentionListener
{
    /** Cache item holding the time of the last run (shown on the admin status page) */
    public const CACHE_KEY = 'data_retention_purged';

    public function __construct(
        private DataRetention $retention,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Also after a failure it waits a day, so a broken purge doesn't run (and log) on every request
        $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): int {
            $item->expiresAfter(86400);
            try {
                $deleted = $this->retention->purge();
                if (array_sum($deleted) > 0) {
                    $this->logger->info('Old personal data deleted', $deleted);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Deleting old personal data failed: ' . $e->getMessage(), ['exception' => $e]);
            }

            return time();
        });
    }
}
