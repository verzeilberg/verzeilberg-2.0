<?php

declare(strict_types=1);

namespace App\Tests\Steam;

use App\Entity\Role;
use App\Entity\Steam\SteamGame;
use App\Entity\User;
use App\Service\Steam\SteamApiClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The public game library, the home page block and the admin page.
 */
class SteamPagesTest extends WebTestCase
{
    use SteamTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->beginSteamTransaction();
        static::getContainer()->set(SteamApiClient::class, $this->steamClient());
    }

    protected function tearDown(): void
    {
        $this->rollBackSteamTransaction();
        parent::tearDown();
    }

    public function testLibraryShowsPlayedVisibleGamesWithFiltersAndSorting(): void
    {
        $this->game(10, 'Factorio', 31500, '-2 days', ['Simulation', 'Strategy'], [12, 40]);
        $this->game(20, 'Portal 2', 900, '-1 year', ['Action', 'Puzzle'], [51, 51]);
        $this->game(30, 'Never Played', 0, null, ['Action']);
        $this->game(40, 'Secret Game', 60, '-1 day', ['Action'])->setHidden(true);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/games');
        $this->assertResponseIsSuccessful();
        $this->assertSame(['Factorio', 'Portal 2'], $this->libraryNames($crawler), 'played games, last played first; hidden left out');
        $this->assertSame(['Factorio', 'Portal 2'], $crawler->filter('h2:contains("Recent gespeeld") + .row .card-title')->each(fn ($node) => $node->text()));
        $this->assertSame(['3', '2', '540', '63'], $crawler->filter('.stat-tile-value')->each(fn ($node) => $node->text()), 'games, played, hours, achievements (hidden left out)');
        $this->assertStringContainsString('12/40', $crawler->filter('.steam-game')->first()->text());
        $this->assertSame(['', 'Action', 'Puzzle', 'Simulation', 'Strategy'], $crawler->filter('#gameGenre option')->each(fn ($node) => $node->attr('value')));

        $this->assertSame(['Never Played', 'Portal 2'], $this->libraryNames($this->client->request('GET', '/games?show=all&genre=Action&order=name')));
        $this->assertSame(['Portal 2'], $this->libraryNames($this->client->request('GET', '/games?q=port')));
        $this->assertSame(['Portal 2', 'Factorio'], $this->libraryNames($this->client->request('GET', '/games?order=achievements')));
        $this->assertSame([], $this->libraryNames($this->client->request('GET', '/games?q=100%25')), 'LIKE wildcards are literal');
    }

    public function testEmptyLibraryAndHomeBlock(): void
    {
        $this->client->request('GET', '/games');
        $this->assertSelectorTextContains('.empty-state', 'is nog niet geladen');
        $this->client->request('GET', '/');
        $this->assertSelectorNotExists('.steam-game', 'no block without games');

        $this->game(10, 'Factorio', 600, '-1 day', ['Strategy']);
        $this->em->flush();
        $this->client->request('GET', '/');
        $this->assertSelectorTextContains('.steam-game .card-title', 'Factorio');
    }

    public function testAdminSyncAndHideAGame(): void
    {
        $this->loginAsAdmin();
        $this->ownedGames = [$this->owned(10, 'Factorio', 600, '-1 day'), $this->owned(20, 'Portal 2', 90, '-1 week')];
        $this->storeDetails = [10 => $this->store('Factorio', ['Strategy']), 20 => $this->store('Portal 2')];
        $this->ownedGames[] = $this->owned(30, '7 Days to Die', 5000, '-3 days');
        $this->storeDetails[30] = $this->store('7 Days to Die');
        $this->achievements = [10 => [3, 10], 20 => null, 30 => 'private'];
        $this->schemaTotals = [30 => 43];

        $crawler = $this->client->request('GET', '/admin/steam/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.card-body', 'Nog niet gesynchroniseerd');
        $this->client->submit($crawler->filter('form[action$="/admin/steam/sync"]')->form());
        $this->assertResponseRedirects('/admin/steam/');

        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'Steam gesynchroniseerd: 3 toegevoegd');
        $this->assertSame(['Factorio', '7 Days to Die', 'Portal 2'], $crawler->filter('tbody tr td:first-child a[href*="/admin/steam/game/"]')->each(fn ($node) => $node->text()));
        $this->assertStringContainsString('Privé in Steam (43 prestaties)', $crawler->filter('tbody tr')->eq(1)->text());
        $this->assertStringContainsString('Geen', $crawler->filter('tbody tr')->eq(2)->text(), 'Portal 2 has no achievements');

        // Hide Factorio: gone from the public page, still in the admin
        $this->client->submit($crawler->filter('tbody tr')->first()->filter('form')->form());
        $this->assertResponseRedirects('/admin/steam/');
        $this->assertTrue($this->em->getRepository(SteamGame::class)->findOneBy(['appId' => 10])?->isHidden());
        $this->assertSame(['7 Days to Die', 'Portal 2'], $this->libraryNames($this->client->request('GET', '/games')));
    }

    public function testAdminTableSortsOnEveryColumnHeaderBothWays(): void
    {
        $this->loginAsAdmin();
        $this->game(10, 'Factorio', 31500, '-2 days', ['Strategy'], [12, 40]);            // 30%
        $this->game(20, 'Portal 2', 900, '-1 year', ['Action'], [51, 51]);                // 100%
        $this->game(30, 'Among Us', 600, '-1 month', ['Casual'])->setAchievementsPrivate(33);
        $this->game(40, 'Never Played', 0, null, ['Action'])->setHidden(true);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/steam/');
        $this->assertSame(['Factorio', 'Among Us', 'Portal 2', 'Never Played'], $this->adminNames($crawler), 'default: last played; never played last');

        // First click: the column's own direction; second click: reversed (empty values stay last)
        foreach ([
            'Game' => [['Among Us', 'Factorio', 'Never Played', 'Portal 2'], ['Portal 2', 'Never Played', 'Factorio', 'Among Us']],
            'Speeltijd' => [['Factorio', 'Portal 2', 'Among Us', 'Never Played'], ['Never Played', 'Among Us', 'Portal 2', 'Factorio']],
            'Prestaties' => [['Portal 2', 'Factorio', 'Among Us', 'Never Played'], ['Factorio', 'Portal 2', 'Among Us', 'Never Played']],
            'Openbaar' => [['Among Us', 'Factorio', 'Portal 2', 'Never Played'], ['Never Played', 'Among Us', 'Factorio', 'Portal 2']],
        ] as $header => [$first, $second]) {
            $crawler = $this->client->click($crawler->filter('thead a')->reduce(fn ($node) => trim($node->text()) === $header)->link());
            $this->assertSame($first, $this->adminNames($crawler), $header . ' first click');
            $this->assertCount(1, $crawler->filter('thead th[aria-sort]'));

            $crawler = $this->client->click($crawler->filter('thead a')->reduce(fn ($node) => trim($node->text()) === $header)->link());
            $this->assertSame($second, $this->adminNames($crawler), $header . ' second click');
        }

        // Searching keeps the sort, the sort keeps the search
        $crawler = $this->client->request('GET', '/admin/steam/?order=name&dir=desc');
        $form = $crawler->filter('form[role="search"]')->form(['q' => 'o']);
        $crawler = $this->client->submit($form);
        $this->assertSame(['Portal 2', 'Factorio', 'Among Us'], $this->adminNames($crawler), 'names with an "o", Z-A');
        $this->assertStringContainsString('q=o', (string) $crawler->filter('thead a')->first()->attr('href'));

        $this->client->request('GET', '/admin/steam/?order=bogus&dir=sideways');
        $this->assertResponseIsSuccessful('unknown values fall back to the default');
    }

        public function testGamePageShowsAchievementsRarityAndStats(): void
    {
        $this->loginAsAdmin();
        $game = $this->game(251570, '7 Days to Die', 128161, '-2 days', ['Survival']);
        $this->achievements = [251570 => [2, 4]];
        $this->schemaTotals = [251570 => 4];
        $this->userStats = [251570 => ['zombies_killed' => 12345, 'distance_walked' => 1.5]];

        $crawler = $this->client->request('GET', '/admin/steam/');
        $crawler = $this->client->click($crawler->filter('tbody a:contains("7 Days to Die")')->link());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#achievementsSummary .fs-3', '2 van 4');
        $this->assertSelectorTextContains('#achievementsSummary .fs-3', '(50%)');

        // Unlocked first (most recent first), then locked (most common first); a locked hidden one keeps its secret
        $rows = $crawler->filter('.steam-achievement');
        $this->assertSame(['Achievement 1', 'Achievement 0', 'Achievement 2', 'Achievement 3 Verborgen'], $rows->each(fn ($row) => trim($row->filter('.fw-semibold')->first()->text())));
        $this->assertStringContainsString('Ontgrendeld 15 nov 2023', $rows->eq(0)->text());
        $this->assertStringContainsString('Verborgen prestatie', $rows->eq(3)->text());
        $this->assertSame('https://icons.example/3_gray.jpg', $rows->eq(3)->filter('img')->attr('src'), 'locked: grey icon');
        $this->assertSame('https://icons.example/0.jpg', $rows->eq(1)->filter('img')->attr('src'));
        $this->assertStringContainsString('22.5%', $rows->eq(3)->text());
        $this->assertStringNotContainsString('Zeldzaam', $rows->eq(2)->text(), '30%: no badge');
        $this->assertSelectorTextContains('.text-end.small', 'Achievement 1');

        // Filters and the game's own statistics (named when Steam gives a name)
        $this->assertSame(['Alle 4', 'Ontgrendeld 2', 'Vergrendeld 2'], $crawler->filter('.nav-pills a')->each(fn ($node) => trim((string) preg_replace('/\s+/', ' ', $node->text()))));
        $locked = $this->client->request('GET', '/admin/steam/game/' . $game->getId() . '?show=locked');
        $this->assertCount(2, $locked->filter('.steam-achievement.is-locked'));
        $this->assertCount(0, $locked->filter('.steam-achievement.is-unlocked'));
        $this->assertSame(['distance_walked 1,50', 'Zombies killed 12.345'], $crawler->filter('table tr')->each(fn ($row) => implode(' ', $row->filter('td')->each(fn ($cell) => $cell->text()))));

        // The stored count follows Steam; progress is cached until Refresh
        $this->em->clear();
        $this->assertSame(2, $this->em->getRepository(SteamGame::class)->find($game->getId())?->getAchievementsUnlocked());
        $this->requests = [];
        $this->client->request('GET', '/admin/steam/game/' . $game->getId());
        $this->assertSame(0, $this->countRequests('GetPlayerAchievements'), 'cached');
        $this->client->request('GET', '/admin/steam/game/' . $game->getId() . '?refresh=1');
        $this->assertSame(1, $this->countRequests('GetPlayerAchievements'));
        $this->assertSame(0, $this->countRequests('GetSchemaForGame'), 'the achievement list stays cached for a day');
    }

    public function testGamePagePrivateNoAchievementsAndSteamDown(): void
    {
        $this->loginAsAdmin();
        $private = $this->game(10, 'Among Us', 600, '-1 month', ['Casual']);
        $none = $this->game(20, 'Factorio', 31500, '-1 day', ['Strategy']);
        $down = $this->game(30, 'Portal 2', 900, '-1 year', ['Action']);
        $this->achievements = [10 => 'private', 20 => null, 30 => [1, 2]];
        $this->schemaTotals = [10 => 3, 30 => 2];

        $crawler = $this->client->request('GET', '/admin/steam/game/' . $private->getId());
        $this->assertSelectorExists('h2.h5:contains("Je voortgang is privé in Steam")');
        $this->assertCount(3, $crawler->filter('.steam-achievement'), 'the achievements of the game are still listed');
        $this->assertSelectorNotExists('.nav-pills');
        $this->em->clear();
        $this->assertTrue($this->em->getRepository(SteamGame::class)->find($private->getId())?->isAchievementsPrivate());

        $this->client->request('GET', '/admin/steam/game/' . $none->getId());
        $this->assertSelectorTextContains('#achievementsSummary', 'Deze game heeft geen Steam-prestaties');

        $this->playerAchievementsDown = true;
        $this->client->request('GET', '/admin/steam/game/' . $down->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.alert-warning', 'Steam gaf geen antwoord op de aanvraag voor de prestaties');
        $this->assertSelectorTextContains('#achievementsSummary', 'konden nu niet worden opgehaald');
    }

    private function hideSwitch(\Symfony\Component\DomCrawler\Form $form): \Symfony\Component\DomCrawler\Field\ChoiceFormField
    {
        $field = $form['hide'];
        $this->assertInstanceOf(\Symfony\Component\DomCrawler\Field\ChoiceFormField::class, $field);

        return $field;
    }

    private function countRequests(string $method): int
    {
        return count(array_filter($this->requests, fn (string $url) => str_contains($url, $method)));
    }

    public function testActivityCanBeHiddenOnThePublicPages(): void
    {
        $this->loginAsAdmin();
        $this->ownedGames = [$this->owned(10, 'Factorio', 31500, '-2 days', 640), $this->owned(20, 'Portal 2', 900, '-1 year')];
        $this->storeDetails = [10 => $this->store('Factorio'), 20 => $this->store('Portal 2')];
        $crawler = $this->client->request('GET', '/admin/steam/');
        $this->client->submit($crawler->filter('form[action$="/admin/steam/sync"]')->form());

        // Shown by default
        $crawler = $this->client->request('GET', '/games');
        $this->assertSelectorTextContains('.page-lead', 'Speelt nu Factorio');
        $this->assertSelectorExists('#library [title="Laatst gespeeld"]');
        $this->assertStringContainsString('in de afgelopen 2 weken', $crawler->filter('h2:contains("Recent gespeeld") + .row')->text());

        // Switch on in the admin
        $crawler = $this->client->request('GET', '/admin/steam/');
        $form = $crawler->filter('form[action$="/admin/steam/privacy"]')->form();
        $this->hideSwitch($form)->tick();
        $this->client->submit($form);
        $this->assertResponseRedirects('/admin/steam/');
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'tonen niet meer wanneer je speelde');
        $this->assertNotNull($crawler->filter('#steamHideActivity')->attr('checked'), 'the switch is on');

        $crawler = $this->client->request('GET', '/games');
        $this->assertSelectorTextNotContains('.page-lead', 'Speelt nu');
        $this->assertSelectorNotExists('h2:contains("Recent gespeeld")');
        $this->assertSelectorNotExists('[title="Laatst gespeeld"]');
        $this->assertStringNotContainsString('afgelopen 2 weken', $crawler->html());
        $this->assertSame(['playtime', 'name', 'achievements', 'rating'], $crawler->filter('#gameSort option')->each(fn ($node) => $node->attr('value')));
        $this->assertSame(['Factorio', 'Portal 2'], $this->libraryNames($crawler), 'most played first');
        $this->assertSame(['Factorio', 'Portal 2'], $this->libraryNames($this->client->request('GET', '/games?order=recent')), '"last played" order is ignored');
        $this->client->request('GET', '/');
        $this->assertSelectorNotExists('.steam-game');

        // The admin still shows everything; switching off shows it again
        $this->assertStringContainsString('dagen geleden', $this->client->request('GET', '/admin/steam/')->filter('tbody')->text());
        $crawler = $this->client->request('GET', '/admin/steam/');
        $form = $crawler->filter('form[action$="/admin/steam/privacy"]')->form();
        $this->hideSwitch($form)->untick();
        $this->client->submit($form);
        $this->client->request('GET', '/games');
        $this->assertSelectorExists('h2:contains("Recent gespeeld")');
    }

    public function testWriteEditAndDeleteAReview(): void
    {
        $this->loginAsAdmin();
        $game = $this->game(251570, '7 Days to Die', 128161, '-2 days', ['Survival']);
        $this->achievements = [251570 => [2, 4]];
        $this->schemaTotals = [251570 => 4];
        $url = '/admin/steam/game/' . $game->getId();

        // Without a rating and text the review isn't saved
        $crawler = $this->client->request('GET', $url);
        $this->assertSame(['5', '4', '3', '2', '1'], $crawler->filter('.star-rating input')->each(fn ($node) => $node->attr('value')), '5 first in the HTML (CSS shows 1 to 5)');
        $this->client->submit($crawler->filter('#review form')->form());
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('#review', 'Kies een beoordeling van 1 tot 5 sterren.');
        $this->assertSelectorTextContains('#review', 'Schrijf de review.');
        $this->assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM steam_game_review'));

        // A draft: only in the admin
        $form = $this->client->getCrawler()->filter('#review form')->form();
        $form['steam_game_review[rating]'] = '4';
        $form['steam_game_review[body]'] = "Great survival game.\n\nThe <b>blood moons</b> are intense.";
        $this->reviewPublished($form)->untick();
        $this->client->submit($form);
        $this->assertResponseRedirects($url . '#review');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'Review opgeslagen als concept');
        $this->assertSelectorTextContains('#review', 'Concept');
        $this->assertStringContainsString('Concept', $this->client->request('GET', '/admin/steam/')->filter('tbody tr')->first()->text());

        $public = $this->client->request('GET', '/games/251570');
        $this->assertSelectorTextContains('article .empty-state', 'nog geen review van deze game geschreven');
        $this->assertSame([], $this->libraryNames($this->client->request('GET', '/games?show=reviewed')), 'a draft doesn\'t count');

        // Published
        $form = $this->client->request('GET', $url)->filter('#review form')->form();
        $form['steam_game_review[rating]'] = '5';
        $this->reviewPublished($form)->tick();
        $this->client->submit($form);
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'Review opgeslagen en gepubliceerd');

        $public = $this->client->request('GET', '/games/251570');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('article', '5/5 · Uitstekend');
        $this->assertSame(['Great survival game.', 'The <b>blood moons</b> are intense.'], $public->filter('.steam-review-body p')->each(fn ($node) => $node->text()), 'paragraphs; HTML is shown as text');
        $this->assertSame('5 van 5 (Uitstekend)', $public->filter('.steam-stars')->attr('aria-label'));
        $card = $this->client->request('GET', '/games?show=reviewed')->filter('.steam-game')->first();
        $this->assertStringContainsString('Mijn review', $card->text());
        $this->assertSame('/games/251570', $card->filter('a')->first()->attr('href'), 'cards link to the game page');

        // Delete via the modal
        $page = $this->client->request('GET', $url);
        $button = $page->filter('[data-delete-url$="/review/delete"]');
        $this->client->request('POST', (string) $button->attr('data-delete-url'), [], [], ['HTTP_X_CSRF_TOKEN' => (string) $button->attr('data-csrf-token')]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM steam_game_review'));
        $this->assertNull($this->connection->fetchOne('SELECT review_id FROM steam_game WHERE id = ?', [$game->getId()]));
    }

    public function testRatingSortReviewedFilterHiddenGamesAndKeptBySync(): void
    {
        $this->loginAsAdmin();
        $factorio = $this->game(10, 'Factorio', 31500, '-2 days', ['Strategy']);
        $portal = $this->game(20, 'Portal 2', 900, '-1 year', ['Action']);
        $draft = $this->game(30, 'Among Us', 600, '-1 month', ['Casual']);
        $this->game(40, 'Unreviewed', 100, '-1 week', ['Casual']);
        foreach ([[$factorio, 3, true], [$portal, 5, true], [$draft, 1, false]] as [$game, $rating, $published]) {
            $this->em->persist((new \App\Entity\Steam\SteamGameReview($game))->setRating($rating)->setBody('Review of ' . $game->getName())->setPublished($published));
        }
        $this->em->flush();

        $this->assertSame(['Portal 2', 'Factorio', 'Among Us', 'Unreviewed'], $this->libraryNames($this->client->request('GET', '/games?order=rating')), 'a draft rating doesn\'t count publicly');
        $this->assertSame(['Portal 2', 'Factorio'], $this->libraryNames($this->client->request('GET', '/games?show=reviewed&order=rating')));
        $this->assertSame(['Portal 2', 'Factorio', 'Among Us', 'Unreviewed'], $this->adminNames($this->client->request('GET', '/admin/steam/?order=rating')), 'the admin sorts drafts too');
        $this->assertSame(['Among Us', 'Factorio', 'Portal 2', 'Unreviewed'], $this->adminNames($this->client->request('GET', '/admin/steam/?order=rating&dir=asc')));

        // A hidden game has no public page
        // (SQL: after a request the test's entity manager no longer tracks these entities)
        $this->connection->executeStatement('UPDATE steam_game SET hidden = 1 WHERE id = ?', [$portal->getId()]);
        $this->client->request('GET', '/games/20');
        $this->assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/games/999999');
        $this->assertResponseStatusCodeSame(404);

        // Left the library: removed, unless reviewed
        $this->ownedGames = [];
        $crawler = $this->client->request('GET', '/admin/steam/');
        $this->client->submit($crawler->filter('form[action$="/admin/steam/sync"]')->form());
        $this->assertSame(['Among Us', 'Factorio', 'Portal 2'], $this->connection->fetchFirstColumn('SELECT name FROM steam_game ORDER BY name'));
    }

    private function reviewPublished(\Symfony\Component\DomCrawler\Form $form): \Symfony\Component\DomCrawler\Field\ChoiceFormField
    {
        $field = $form['steam_game_review[published]'];
        $this->assertInstanceOf(\Symfony\Component\DomCrawler\Field\ChoiceFormField::class, $field);

        return $field;
    }

    public function testAdminShowsTheSyncError(): void
    {
        $this->loginAsAdmin();
        $this->privateGameDetails = true;

        $crawler = $this->client->request('GET', '/admin/steam/');
        $this->client->submit($crawler->filter('form[action$="/admin/steam/sync"]')->form());
        $this->client->followRedirect();

        $this->assertSelectorTextContains('.alert-dismissible.alert-danger', 'Steam-synchronisatie mislukt');
        $this->assertSelectorTextContains('.card .alert-danger', 'Gamedetails');
    }

    /** @return list<string> game names in the admin table */
    private function adminNames(\Symfony\Component\DomCrawler\Crawler $crawler): array
    {
        return $crawler->filter('tbody tr td:first-child a[href*="/admin/steam/game/"]')->each(fn ($node) => $node->text());
    }

    /** @return list<string> names in the library grid (not the "Recently played" row) */
    private function libraryNames(\Symfony\Component\DomCrawler\Crawler $crawler): array
    {
        return $crawler->filter('#library .steam-game .card-title')->each(fn ($node) => $node->text());
    }

    /**
     * @param list<string>     $genres
     * @param array{int, int}|null $achievements [unlocked, total]
     */
    private function game(int $appId, string $name, int $minutes, ?string $lastPlayed, array $genres, ?array $achievements = null): SteamGame
    {
        $game = (new SteamGame($appId))->setName($name)->setPlaytimeForever($minutes)
            ->setLastPlayedAt($lastPlayed ? new \DateTimeImmutable($lastPlayed) : null)
            ->setGenres($genres)->setDetailsFetchedAt(new \DateTimeImmutable());
        if ($achievements) {
            $game->setAchievements(...$achievements);
        }
        $this->em->persist($game);
        $this->em->flush();

        return $game;
    }

    private function loginAsAdmin(): void
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_ADMIN']) ?? (new Role())->setName('ROLE_ADMIN')->setDescription('ROLE_ADMIN');
        $this->em->persist($role);
        $user = (new User())->setEmail(uniqid('steam-') . '@example.com')->setFullName('Steam test')->setPassword('not-used');
        $user->addRole($role);
        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user, 'primary_auth');
    }
}
