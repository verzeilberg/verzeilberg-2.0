<?php

declare(strict_types=1);

namespace App\Tests\Agenda;

use App\Entity\GoogleAccount;
use App\Entity\Role;
use App\Entity\User;
use App\Service\Google\GoogleCalendarClient;
use App\Service\Google\TokenEncryptor;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Connecting a Google account (OAuth) and choosing calendars, against a simulated Google.
 */
class AgendaGoogleControllerTest extends WebTestCase
{
    use AgendaTestTrait;

    private KernelBrowser $client;
    private string $email;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->beginAgendaTransaction();
        $this->email = uniqid('google-') . '@example.com';

        // A configured Google client that talks to a simulated Google
        $http = new MockHttpClient(function (string $method, string $url, array $options) {
            if (str_contains($url, 'oauth2.googleapis.com/token')) {
                return new MockResponse((string) json_encode(['access_token' => 'access', 'refresh_token' => 'refresh', 'expires_in' => 3599]));
            }
            if (str_contains($url, '/userinfo')) {
                return new MockResponse((string) json_encode(['email' => $this->email]));
            }
            if (str_contains($url, '/calendarList')) {
                return new MockResponse((string) json_encode(['items' => [
                    ['id' => 'primary@gmail.com', 'summary' => 'Prive', 'backgroundColor' => '#9fe1e7', 'primary' => true, 'accessRole' => 'owner'],
                    ['id' => 'club@group.calendar.google.com', 'summary' => 'Handbal', 'backgroundColor' => '#f83a22', 'accessRole' => 'reader'],
                ]]));
            }
            if (str_contains($url, '/events')) {
                return new MockResponse((string) json_encode(['items' => [
                    ['id' => 'e1', 'status' => 'confirmed', 'summary' => 'Wedstrijd uit Google', 'start' => ['dateTime' => '2030-03-02T14:00:00+01:00'], 'end' => ['dateTime' => '2030-03-02T15:30:00+01:00']],
                ]]));
            }

            return new MockResponse('{}', ['http_code' => 404]);
        });
        static::getContainer()->set(GoogleCalendarClient::class, new GoogleCalendarClient(
            $http,
            static::getContainer()->get(TokenEncryptor::class),
            $this->em,
            'client-id.apps.googleusercontent.com',
            'client-secret'
        ));

        $this->loginAs('ROLE_ADMIN');
    }

    protected function tearDown(): void
    {
        $this->rollBackAgendaTransaction();
        parent::tearDown();
    }

    public function testConnectRedirectsToGoogleWithAState(): void
    {
        $this->client->request('GET', '/admin/agenda/google/connect');

        $this->assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('client-id.apps.googleusercontent.com', $query['client_id']);
        $this->assertSame('offline', $query['access_type'], 'needed to sync without the admin');
        $this->assertStringContainsString('calendar.readonly', (string) $query['scope']);
        $this->assertStringEndsWith('/admin/agenda/google/callback', (string) $query['redirect_uri']);
        $this->assertSame(32, strlen((string) $query['state']));
    }

    public function testCallbackWithAWrongStateIsRefused(): void
    {
        $this->client->request('GET', '/admin/agenda/google/connect');
        $this->client->request('GET', '/admin/agenda/google/callback?code=abc&state=forged');

        $this->assertResponseRedirects('/admin/agenda/agendas');
        $this->assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM google_account WHERE email = ?', [$this->email]));
    }

    public function testConnectChooseCalendarsAndSee(): void
    {
        // OAuth round trip
        $this->client->request('GET', '/admin/agenda/google/connect');
        parse_str((string) parse_url((string) $this->client->getResponse()->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->client->request('GET', '/admin/agenda/google/callback?code=abc&state=' . $query['state']);

        $accountId = (int) $this->connection->fetchOne('SELECT id FROM google_account WHERE email = ?', [$this->email]);
        $this->assertGreaterThan(0, $accountId);
        $this->assertResponseRedirects('/admin/agenda/google/' . $accountId . '/calendars');
        $stored = $this->connection->fetchAssociative('SELECT access_token, refresh_token FROM google_account WHERE id = ?', [$accountId]);
        $this->assertNotSame('refresh', $stored['refresh_token'], 'stored encrypted');

        // Choose the club calendar
        $crawler = $this->client->request('GET', '/admin/agenda/google/' . $accountId . '/calendars');
        $this->assertSame(['Prive', 'Handbal'], $crawler->filter('.list-group-item')->each(fn ($row) => trim((string) preg_replace('/\s+hoofdagenda/', '', $row->text()))));
        $this->client->request('POST', '/admin/agenda/google/' . $accountId . '/calendars', [
            '_token' => $crawler->filter('input[name="_token"]')->attr('value'),
            'calendars' => ['club@group.calendar.google.com'],
        ]);
        $this->assertResponseRedirects('/admin/agenda/agendas');

        $agenda = $this->connection->fetchAssociative('SELECT id, title, color, google_calendar_id FROM agenda WHERE google_account_id = ?', [$accountId]);
        $this->assertSame(['Handbal', '#f83a22', 'club@group.calendar.google.com'], [$agenda['title'], $agenda['color'], $agenda['google_calendar_id']]);
        $item = $this->connection->fetchAssociative('SELECT id, title, start_at FROM agenda_item WHERE agenda_id = ?', [$agenda['id']]);
        $this->assertSame(['Wedstrijd uit Google', '2030-03-02 14:00:00'], [$item['title'], $item['start_at']]);

        // Visible in the calendar, but read-only
        $html = $this->client->request('GET', '/admin/agenda/?view=day&date=2030-03-02')->html();
        $this->assertStringContainsString('Wedstrijd uit Google', $html);
        $this->assertStringContainsString('data-google="1"', $html);
        $this->assertStringNotContainsString('/admin/agenda/item/' . $item['id'] . '/edit', $html);

        $this->client->request('GET', '/admin/agenda/item/' . $item['id'] . '/edit');
        $this->assertResponseRedirects();
        $form = $this->client->request('GET', '/admin/agenda/item/new')->filter('select[name="agenda_item[agenda]"] option')->each(fn ($option) => $option->text());
        $this->assertNotContains('Handbal', $form, 'Google agendas are not offered for new items');
    }

    public function testDisconnectUnlinksAgendasButKeepsTheirItems(): void
    {
        $encryptor = static::getContainer()->get(TokenEncryptor::class);
        $account = new GoogleAccount($this->email, $encryptor->encrypt('a'), $encryptor->encrypt('r'), new \DateTimeImmutable('+1 hour'));
        $this->em->persist($account);
        $agenda = $this->createAgenda('Google');
        $agenda->linkToGoogle($account, 'cal');
        $this->em->flush();
        $this->createItem($agenda, 'Blijft staan', '2030-01-01 10:00', '2030-01-01 11:00');

        $html = $this->client->request('GET', '/admin/agenda/agendas')->html();
        preg_match('#data-delete-url="/admin/agenda/google/' . $account->getId() . '/disconnect"\s+data-csrf-token="([^"]+)"#', $html, $match);
        $this->client->request('POST', '/admin/agenda/google/' . $account->getId() . '/disconnect', [], [], ['HTTP_X_CSRF_TOKEN' => $match[1] ?? '']);

        $this->assertResponseIsSuccessful();
        $this->assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM google_account WHERE email = ?', [$this->email]));
        $this->assertNull($this->connection->fetchOne('SELECT google_calendar_id FROM agenda WHERE id = ?', [$agenda->getId()]));
        $this->assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM agenda_item WHERE agenda_id = ?', [$agenda->getId()]));
    }

    private function loginAs(string $roleName): void
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => $roleName]) ?? (new Role())->setName($roleName)->setDescription($roleName);
        $this->em->persist($role);
        $user = new User();
        $user->setEmail(uniqid('agenda-google-') . '@example.com');
        $user->setFullName('Agenda Google test');
        $user->setPassword('not-used');
        $user->addRole($role);
        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user, 'primary_auth');
    }
}
