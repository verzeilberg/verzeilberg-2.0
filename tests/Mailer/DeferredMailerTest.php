<?php

declare(strict_types=1);

namespace App\Tests\Mailer;

use App\Mailer\DeferredMailer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * During a web request mails go out after the response (kernel.terminate); without a request (a command)
 * right away. The last success and failure are kept for the status page.
 */
class DeferredMailerTest extends TestCase
{
    /** @var list<RawMessage> */
    private array $sent = [];
    private bool $fail = false;
    private ArrayAdapter $cache;
    private RequestStack $requestStack;
    private DeferredMailer $mailer;

    protected function setUp(): void
    {
        $inner = new class($this) implements MailerInterface {
            public function __construct(private DeferredMailerTest $test) {}

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->test->deliver($message);
            }
        };
        $this->cache = new ArrayAdapter();
        $this->requestStack = new RequestStack();
        $this->mailer = new DeferredMailer($inner, $this->requestStack, $this->cache, new NullLogger());
    }

    public function deliver(RawMessage $message): void
    {
        if ($this->fail) {
            throw new TransportException('Connection refused');
        }
        $this->sent[] = $message;
    }

    public function testDuringARequestMailIsSentAfterTheResponse(): void
    {
        $this->requestStack->push($request = new Request());

        $this->mailer->send($this->email());
        $this->assertSame([], $this->sent);

        $this->mailer->onTerminate($this->terminate($request));
        $this->assertCount(1, $this->sent);
        $this->assertIsInt($this->cache->getItem(DeferredMailer::LAST_SENT_KEY)->get());

        // Sent only once
        $this->mailer->onTerminate($this->terminate($request));
        $this->assertCount(1, $this->sent);
    }

    public function testWithoutARequestMailIsSentRightAway(): void
    {
        $this->mailer->send($this->email());

        $this->assertCount(1, $this->sent);
    }

    public function testAFailureIsRecordedAndDoesNotStopTheOtherMails(): void
    {
        $this->requestStack->push($request = new Request());
        $this->mailer->send($this->email());
        $this->mailer->send($this->email());
        $this->fail = true;

        $this->mailer->onTerminate($this->terminate($request));

        $failure = $this->cache->getItem(DeferredMailer::LAST_FAILURE_KEY)->get();
        $this->assertSame('Connection refused', $failure['error']);
    }

    public function testSendNowReportsAFailure(): void
    {
        $this->fail = true;

        $this->expectException(TransportException::class);
        $this->mailer->sendNow($this->email());
    }

    private function email(): Email
    {
        return (new Email())->from('a@example.com')->to('b@example.com')->subject('Test')->text('Test');
    }

    private function terminate(Request $request): TerminateEvent
    {
        return new TerminateEvent($this->createStub(HttpKernelInterface::class), $request, new Response());
    }
}
