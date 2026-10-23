<?php

declare(strict_types=1);

namespace App\Tests\Agenda;

use App\Entity\Agenda;
use App\Entity\AgendaItem;
use App\Entity\GoogleAccount;
use App\Repository\AgendaItemRepository;
use App\Repository\AgendaRepository;
use App\Service\Google\GoogleApiException;
use App\Service\Google\GoogleCalendarClient;
use App\Service\Google\GoogleCalendarSync;
use App\Service\Google\TokenEncryptor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Google Calendar sync against a simulated Google API (MockHttpClient).
 */
class GoogleCalendarTest extends KernelTestCase
{
    use AgendaTestTrait;

    private TokenEncryptor $encryptor;
    /** @var list<array{method: string, url: string}> requests made to "Google" */
    private array $requests = [];
    /** @var list<list<array<string, mixed>>> events "Google" returns: one list per page */
    private array $eventPages = [];
    private int $tokenStatus = 200;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->beginAgendaTransaction();
        $this->encryptor = static::getContainer()->get(TokenEncryptor::class);
    }

    protected function tearDown(): void
    {
        $this->rollBackAgendaTransaction();
        parent::tearDown();
    }

    public function testTokensAreStoredEncrypted(): void
    {
        $encrypted = $this->encryptor->encrypt('ya29.secret-access-token');

        $this->assertStringNotContainsString('secret', $encrypted);
        $this->assertSame('ya29.secret-access-token', $this->encryptor->decrypt($encrypted));
        $this->assertNotSame($encrypted, $this->encryptor->encrypt('ya29.secret-access-token'), 'random nonce');

        $this->expectException(\RuntimeException::class);
        $this->encryptor->decrypt(base64_encode(str_repeat('x', 40)));
    }

    public function testSyncCopiesEventsAndFollowsGoogle(): void
    {
        $agenda = $this->linkedAgenda();
        $this->eventPages = [
            // page 1
            [
                ['id' => 'camp', 'status' => 'confirmed', 'summary' => 'Kamp', 'start' => ['date' => '2026-10-10'], 'end' => ['date' => '2026-10-13']],
                ['id' => 'match', 'status' => 'confirmed', 'summary' => 'Wedstrijd', 'location' => 'Sporthal', 'description' => 'Neem <b>tenue</b> mee<br>Vertrek 13:00 &amp; terug 18:00',
                    'start' => ['dateTime' => '2026-10-03T14:00:00+02:00'], 'end' => ['dateTime' => '2026-10-03T15:30:00+02:00']],
            ],
            // page 2
            [
                ['id' => 'utc', 'status' => 'confirmed', 'summary' => 'In UTC', 'start' => ['dateTime' => '2026-12-01T18:00:00Z'], 'end' => ['dateTime' => '2026-12-01T19:00:00Z']],
                ['id' => 'gone', 'status' => 'cancelled'],
            ],
        ];

        $result = $this->sync()->sync($agenda);

        $this->assertSame(['created' => 3, 'updated' => 0, 'deleted' => 0], $result);
        $items = $this->items($agenda);
        $this->assertSame(['2026-10-10 00:00', '2026-10-12 23:59', true], [$items['Kamp']->getStartAt()->format('Y-m-d H:i'), $items['Kamp']->getEndAt()->format('Y-m-d H:i'), $items['Kamp']->isAllDay()]);
        $this->assertSame('14:00-15:30', $items['Wedstrijd']->getStartAt()->format('H:i') . '-' . $items['Wedstrijd']->getEndAt()->format('H:i'));
        $this->assertSame("Neem tenue mee\nVertrek 13:00 & terug 18:00", $items['Wedstrijd']->getDescription(), 'HTML from Google becomes plain text');
        $this->assertSame('19:00', $items['In UTC']->getStartAt()->format('H:i'), '18:00 UTC = 19:00 in Amsterdam (winter time)');
        $this->assertNotNull($agenda->getLastSyncedAt());
        $this->assertSame(2, count(array_filter($this->requests, fn ($r) => str_contains($r['url'], '/events'))), 'both pages fetched');

        // Changed in Google: title updated, "camp" removed
        $this->eventPages = [[
            ['id' => 'match', 'status' => 'confirmed', 'summary' => 'Wedstrijd (verplaatst)', 'start' => ['dateTime' => '2026-10-03T16:00:00+02:00'], 'end' => ['dateTime' => '2026-10-03T17:30:00+02:00']],
            ['id' => 'utc', 'status' => 'confirmed', 'summary' => 'In UTC', 'start' => ['dateTime' => '2026-12-01T18:00:00Z'], 'end' => ['dateTime' => '2026-12-01T19:00:00Z']],
        ]];
        $result = $this->sync()->sync($agenda);

        $this->assertSame(['created' => 0, 'updated' => 2, 'deleted' => 1], $result);
        $this->assertSame(['In UTC', 'Wedstrijd (verplaatst)'], array_keys($this->items($agenda)));
    }

    public function testHistoryOutsideTheWindowIsKept(): void
    {
        $agenda = $this->linkedAgenda();
        $old = (new AgendaItem())->setAgenda($agenda)->setTitle('Lang geleden')->setUid('google:old')
            ->setStartAt(new \DateTimeImmutable('-3 years'))->setEndAt(new \DateTimeImmutable('-3 years +1 hour'));
        $this->em->persist($old);
        $this->em->flush();
        $this->eventPages = [[]];

        $this->sync()->sync($agenda);

        $this->assertArrayHasKey('Lang geleden', $this->items($agenda));
    }

    public function testExpiredAccessTokenIsRenewed(): void
    {
        $agenda = $this->linkedAgenda(expired: true);
        $this->eventPages = [[]];

        $this->sync()->sync($agenda);

        $this->assertSame('POST', $this->requests[0]['method']);
        $this->assertStringContainsString('oauth2.googleapis.com/token', $this->requests[0]['url']);
        $account = $agenda->getGoogleAccount();
        $this->assertSame('new-access-token', $this->encryptor->decrypt((string) $account?->getEncryptedAccessToken()));
        $this->assertGreaterThan(new \DateTimeImmutable('+30 minutes'), $account?->getAccessTokenExpiresAt());
    }

    public function testRefusedRefreshTokenIsReportedOnTheAccountAndAgenda(): void
    {
        $agenda = $this->linkedAgenda(expired: true);
        $this->tokenStatus = 400;

        try {
            $this->sync()->sync($agenda);
            $this->fail('A refused refresh token must stop the sync');
        } catch (GoogleApiException $e) {
            $this->assertStringContainsString('expired or revoked', $e->getMessage());
        }

        $this->assertStringContainsString('koppel het account opnieuw', (string) $agenda->getGoogleAccount()?->getLastError());
        $this->assertNotNull($agenda->getLastSyncError());
    }

    public function testSyncAllSkipsNormalAgendasAndContinuesAfterAnError(): void
    {
        $this->createAgenda('Normal');
        $broken = $this->linkedAgenda(expired: true);
        $this->tokenStatus = 400;

        $results = $this->sync()->syncAll();

        $this->assertArrayHasKey($broken->getTitle(), $results);
        $this->assertIsString($results[$broken->getTitle()], 'the error is returned, not thrown');
        $this->assertSame([], array_filter(array_keys($results), fn (string $title) => str_starts_with($title, 'Normal')), 'agendas without Google link are skipped');
    }

    private function linkedAgenda(bool $expired = false): Agenda
    {
        $account = new GoogleAccount(
            uniqid('google-') . '@example.com',
            $this->encryptor->encrypt('access-token'),
            $this->encryptor->encrypt('refresh-token'),
            new \DateTimeImmutable($expired ? '-1 minute' : '+1 hour')
        );
        $this->em->persist($account);
        $agenda = $this->createAgenda('Google');
        $agenda->linkToGoogle($account, 'calendar@group.calendar.google.com');
        $this->em->flush();

        return $agenda;
    }

    private function sync(): GoogleCalendarSync
    {
        $page = 0;
        $http = new MockHttpClient(function (string $method, string $url) use (&$page) {
            $this->requests[] = ['method' => $method, 'url' => $url];
            if (str_contains($url, 'oauth2.googleapis.com/token')) {
                return $this->tokenStatus === 200
                    ? new MockResponse((string) json_encode(['access_token' => 'new-access-token', 'expires_in' => 3599]))
                    : new MockResponse((string) json_encode(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.']), ['http_code' => $this->tokenStatus]);
            }
            if (str_contains($url, '/events')) {
                $items = $this->eventPages[$page] ?? [];
                $next = isset($this->eventPages[$page + 1]) ? ['nextPageToken' => 'page' . ($page + 1)] : [];
                ++$page;

                return new MockResponse((string) json_encode(['items' => $items] + $next));
            }

            return new MockResponse('{}', ['http_code' => 404]);
        });
        $client = new GoogleCalendarClient($http, $this->encryptor, $this->em, 'client-id', 'client-secret');

        return new GoogleCalendarSync(
            $client,
            static::getContainer()->get(AgendaRepository::class),
            static::getContainer()->get(AgendaItemRepository::class),
            $this->em,
            'Europe/Amsterdam'
        );
    }

    /** @return array<string, AgendaItem> by title */
    private function items(Agenda $agenda): array
    {
        $items = [];
        foreach ($this->em->getRepository(AgendaItem::class)->findBy(['agenda' => $agenda], ['title' => 'ASC']) as $item) {
            $items[$item->getTitle()] = $item;
        }

        return $items;
    }
}
