<?php

declare(strict_types=1);

namespace App\Tests\Agenda;

use App\Entity\Role;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;

class AgendaControllerTest extends WebTestCase
{
    use AgendaTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->beginAgendaTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollBackAgendaTransaction();
        parent::tearDown();
    }

    public function testSiteShowsOnlyPublicAgendas(): void
    {
        $public = $this->createAgenda('Club', public: true);
        $private = $this->createAgenda('Bestuur', public: false);
        $this->createItem($public, 'Open training', '2030-05-14 19:00', '2030-05-14 20:30');
        $this->createItem($private, 'Geheime vergadering', '2030-05-15 20:00', '2030-05-15 22:00');

        $crawler = $this->client->request('GET', '/agenda?view=month&date=2030-05-01');

        $this->assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Open training', $html);
        $this->assertStringNotContainsString('Geheime vergadering', $html);
        $this->assertSame(0, $crawler->filter('a[href*="/admin/"]')->count(), 'no admin links on the site');
        $this->assertSelectorExists('a[href^="webcal://"]');
    }

    public function testFeedNeedsTheTokenAndStopsWhenArchived(): void
    {
        $agenda = $this->createAgenda('Feed', public: false);
        $this->createItem($agenda, 'Wedstrijd', '2030-01-15 16:00', '2030-01-15 17:30');
        $token = $agenda->getFeedToken();
        // Let the request load the agenda (and its items) from the database, like a real request
        $this->em->clear();

        $this->client->request('GET', '/agenda/feed/' . $token . '.ics');
        $this->assertResponseIsSuccessful();
        $this->assertStringStartsWith('text/calendar', (string) $this->client->getResponse()->headers->get('Content-Type'));
        $this->assertStringContainsString('SUMMARY:Wedstrijd', $this->client->getResponse()->getContent());

        $this->client->request('GET', '/agenda/feed/' . str_repeat('0', 48) . '.ics');
        $this->assertResponseStatusCodeSame(404);

        $this->connection->executeStatement('UPDATE agenda SET deleted = 1 WHERE feed_token = ?', [$token]);
        $this->client->request('GET', '/agenda/feed/' . $token . '.ics');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testAdminCanCreateAnAgendaAndItems(): void
    {
        $this->loginAs('ROLE_ADMIN');

        $form = $this->client->request('GET', '/admin/agenda/new')->filter('form[name="agenda"]')->form();
        $form['agenda[title]'] = 'Handbal ' . uniqid();
        $form['agenda[color]'] = '#ff8800';
        $this->tick($form['agenda[public]']);
        $this->client->submit($form);
        $this->assertResponseRedirects('/admin/agenda/agendas');
        $agendaId = (int) $this->connection->fetchOne('SELECT id FROM agenda WHERE color = ? ORDER BY id DESC', ['#ff8800']);

        // Timed item
        $form = $this->client->request('GET', '/admin/agenda/item/new?date=2030-03-02&agenda=' . $agendaId)->filter('form[name="agenda_item"]')->form();
        $this->assertSame('2030-03-02', $form['agenda_item[startDate]']->getValue(), 'date taken from the calendar');
        $form['agenda_item[title]'] = 'Training';
        $form['agenda_item[startTime]'] = '19:00';
        $form['agenda_item[endTime]'] = '20:30';
        $this->client->submit($form);
        $this->assertResponseRedirects();

        // Whole-day item over several days
        $form = $this->client->request('GET', '/admin/agenda/item/new?date=2030-07-10&agenda=' . $agendaId)->filter('form[name="agenda_item"]')->form();
        $form['agenda_item[title]'] = 'Kamp';
        $this->tick($form['agenda_item[allDay]']);
        $form['agenda_item[endDate]'] = '2030-07-12';
        $this->client->submit($form);

        $rows = $this->connection->fetchAllAssociative('SELECT title, start_at, end_at, all_day FROM agenda_item WHERE agenda_id = ? ORDER BY start_at', [$agendaId]);
        $this->assertSame([
            ['title' => 'Training', 'start_at' => '2030-03-02 19:00:00', 'end_at' => '2030-03-02 20:30:00', 'all_day' => 0],
            ['title' => 'Kamp', 'start_at' => '2030-07-10 00:00:00', 'end_at' => '2030-07-12 23:59:59', 'all_day' => 1],
        ], array_map(fn ($row) => ['title' => $row['title'], 'start_at' => $row['start_at'], 'end_at' => $row['end_at'], 'all_day' => (int) $row['all_day']], $rows));

        // Shown in the admin calendar
        $this->client->request('GET', '/admin/agenda/?view=week&date=2030-03-02');
        $this->assertStringContainsString('Training', $this->client->getResponse()->getContent());
    }

    public function testLocationIsShownOnGoogleMaps(): void
    {
        $agenda = $this->createAgenda('Maps', public: true);
        $item = $this->createItem($agenda, 'Wedstrijd', '2030-05-14 19:00', '2030-05-14 20:30');
        $item->setLocation('Sporthal De Bloemhof, Aalsmeer');
        $this->em->flush();

        // Site and admin: the item carries the location, the modal has the map and the links (filled by the script)
        foreach (['/agenda?view=day&date=2030-05-14', '/admin/agenda/?view=day&date=2030-05-14'] as $url) {
            if (str_starts_with($url, '/admin')) {
                $this->loginAs('ROLE_ADMIN');
            }
            $crawler = $this->client->request('GET', $url);
            $this->assertSame('Sporthal De Bloemhof, Aalsmeer', $crawler->filter('.agenda-item')->attr('data-location'), $url);
            $this->assertSelectorExists('#agendaItemModal iframe.js-agenda-map');
            $this->assertSelectorExists('#agendaItemModal .js-agenda-route-link');
            $this->assertSame('https://www.google.com/maps/embed/v1/place?key=test-maps-key', $crawler->filter('#agendaItemModal iframe.js-agenda-map')->attr('data-embed-url'));
        }

        // Item form: Google place search next to the normal location field
        $this->client->request('GET', '/admin/agenda/item/' . $item->getId() . '/edit');
        $this->assertSelectorExists('#locationSearch');
        $this->assertSelectorExists('input[name="agenda_item[location]"][value="Sporthal De Bloemhof, Aalsmeer"]');
        $this->assertStringContainsString('maps.googleapis.com/maps/api/js?key=test-maps-key&loading=async&callback=initAgendaLocationSearch', $this->client->getResponse()->getContent());
    }

    public function testItemMustEndAfterItStartsAndNeedsTimes(): void
    {
        $this->loginAs('ROLE_ADMIN');
        $agenda = $this->createAgenda();

        $form = $this->client->request('GET', '/admin/agenda/item/new?date=2030-03-02&agenda=' . $agenda->getId())->filter('form[name="agenda_item"]')->form();
        $form['agenda_item[title]'] = 'Verkeerd om';
        $form['agenda_item[startTime]'] = '20:00';
        $form['agenda_item[endTime]'] = '19:00';
        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('The end must be after the start.', $this->client->getResponse()->getContent());

        $form['agenda_item[startTime]'] = '';
        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('Enter a start time', $this->client->getResponse()->getContent());
    }

    public function testDeletingAnItemNeedsAValidToken(): void
    {
        $this->loginAs('ROLE_ADMIN');
        $agenda = $this->createAgenda();
        $item = $this->createItem($agenda, 'Weg ermee', '2030-03-02 10:00', '2030-03-02 11:00');
        $url = '/admin/agenda/item/' . $item->getId() . '/delete';

        $this->client->request('POST', $url, [], [], ['HTTP_X_CSRF_TOKEN' => 'invalid']);
        $this->assertResponseStatusCodeSame(403);

        $html = $this->client->request('GET', '/admin/agenda/?view=day&date=2030-03-02')->html();
        preg_match('/data-delete-url="' . preg_quote($url, '/') . '"\s+data-csrf-token="([^"]+)"/', $html, $match);
        $this->client->request('POST', $url, [], [], ['HTTP_X_CSRF_TOKEN' => $match[1] ?? '']);
        $this->assertResponseIsSuccessful();
        $this->assertFalse($this->connection->fetchOne('SELECT id FROM agenda_item WHERE id = ?', [$item->getId()]));
    }

    public function testAgendaAdminIsOnlyForAdmins(): void
    {
        $this->client->request('GET', '/admin/agenda/');
        $this->assertResponseRedirects();

        $this->loginAs('ROLE_USER');
        $this->client->request('GET', '/admin/agenda/');
        $this->assertResponseStatusCodeSame(403);
    }

    /** @param mixed $field a checkbox of a DomCrawler form */
    private function tick(mixed $field): void
    {
        $this->assertInstanceOf(ChoiceFormField::class, $field);
        $field->tick();
    }

    private function loginAs(string $roleName): void
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => $roleName]);
        if (!$role) {
            $role = new Role();
            $role->setName($roleName);
            $role->setDescription($roleName);
            $this->em->persist($role);
        }
        $user = new User();
        $user->setEmail(uniqid('agenda-test-') . '@example.com');
        $user->setFullName('Agenda test');
        $user->setPassword('not-used');
        $user->addRole($role);
        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user, 'primary_auth');
    }
}
