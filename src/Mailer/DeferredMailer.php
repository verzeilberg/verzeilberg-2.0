<?php

declare(strict_types=1);

namespace App\Mailer;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Mails sent during a web request go out after the page has been sent to the visitor (kernel.terminate),
 * so a slow mail server doesn't slow down registering, posting a comment or logging in. There is no queue
 * worker (the server has no shell, and cronjobs aren't sure): the same PHP process sends them right after.
 * A mail that can't be sent is logged; the last success and failure are shown on the admin status page.
 * Commands send right away, and so does sendNow() (the status page's test mail, which reports the result).
 */
#[AsDecorator('mailer.mailer')]
class DeferredMailer implements MailerInterface, ResetInterface
{
    /** Cache items with the time of the last mail sent, and [time, error] of the last one that failed */
    public const LAST_SENT_KEY = 'mailer_last_sent';
    public const LAST_FAILURE_KEY = 'mailer_last_failure';

    /** @var list<array{RawMessage, ?Envelope}> */
    private array $queue = [];

    public function __construct(
        #[AutowireDecorated] private MailerInterface $inner,
        private RequestStack $requestStack,
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if ($this->requestStack->getMainRequest() === null) {
            $this->sendNow($message, $envelope);
            return;
        }

        $this->queue[] = [$message, $envelope];
    }

    /** Sends at once; a failure is recorded and thrown */
    public function sendNow(RawMessage $message, ?Envelope $envelope = null): void
    {
        try {
            $this->inner->send($message, $envelope);
        } catch (\Throwable $e) {
            $this->remember(self::LAST_FAILURE_KEY, ['time' => time(), 'error' => $e->getMessage()]);
            throw $e;
        }

        $this->remember(self::LAST_SENT_KEY, time());
    }

    #[AsEventListener(event: KernelEvents::TERMINATE, priority: 100)]
    public function onTerminate(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $queue = $this->queue;
        $this->queue = [];
        foreach ($queue as [$message, $envelope]) {
            try {
                $this->sendNow($message, $envelope);
            } catch (\Throwable $e) {
                $this->logger->error('Sending a mail failed: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }

    /** Between requests (a worker process or the test client handles several) */
    public function reset(): void
    {
        $this->queue = [];
    }

    private function remember(string $key, mixed $value): void
    {
        try {
            $this->cache->save($this->cache->getItem($key)->set($value));
        } catch (\Throwable) {
            // Only for the status page: never let this stop a mail
        }
    }
}
