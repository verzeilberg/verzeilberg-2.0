<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\CheckList;
use App\Entity\Friendship;
use App\Tests\Checklist\ChecklistTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Filling in checklists outside the admin, per access setting, and "My submissions".
 */
class CheckListControllerTest extends WebTestCase
{
    use ChecklistTestTrait;

    protected function setUp(): void
    {
        $this->setUpChecklistTest();
    }

    protected function tearDown(): void
    {
        $this->tearDownChecklistTest();
        parent::tearDown();
    }

    public function testAdminOnlyChecklistDoesNotExistOutsideTheAdmin(): void
    {
        $checkList = $this->checkListWithField(CheckList::ACCESS_ADMIN);

        $this->client->request('GET', '/checklists/' . $checkList->getId() . '/fill');
        $this->assertResponseStatusCodeSame(404);

        $this->loginAs($this->createUser());
        $this->client->request('GET', '/checklists/' . $checkList->getId() . '/fill');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testArchivedChecklistIsNotOpen(): void
    {
        $checkList = $this->checkListWithField(CheckList::ACCESS_PUBLIC);
        $checkList->setDeleted(true);
        $this->em->flush();

        $this->client->request('GET', '/checklists/' . $checkList->getId() . '/fill');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAnyoneCanFillInAPublicChecklist(): void
    {
        $checkList = $this->checkListWithField(CheckList::ACCESS_PUBLIC);

        $crawler = $this->client->request('GET', '/checklists');
        $this->assertStringContainsString($checkList->getName(), $crawler->filter('main')->text());

        $form = $this->client->request('GET', '/checklists/' . $checkList->getId() . '/fill')->filter('form[name="check_list_item"]')->form();
        $form['check_list_item[field_naam]'] = 'Anoniem';
        $this->client->submit($form);

        $this->assertResponseRedirects('/checklists/' . $checkList->getId() . '/thanks');
        $row = $this->connection->fetchAssociative('SELECT item_content, created_by FROM checklist_items WHERE check_list_id = ?', [$checkList->getId()]);
        $this->assertSame(['field_naam' => 'Anoniem'], json_decode($row['item_content'], true));
        $this->assertNull($row['created_by']);
    }

    public function testLoggedInChecklistAsksAnonymousVisitorsToLogIn(): void
    {
        $checkList = $this->checkListWithField(CheckList::ACCESS_USER);

        $this->client->request('GET', '/checklists/' . $checkList->getId() . '/fill');
        $this->assertResponseRedirects();
        $this->assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));

        $crawler = $this->client->request('GET', '/checklists');
        $this->assertStringNotContainsString($checkList->getName(), $crawler->filter('main')->text());
    }

    public function testFriendsChecklistIsOnlyForTheOwnersFriends(): void
    {
        $owner = $this->createUser();
        $friend = $this->createUser();
        $stranger = $this->createUser();
        $checkList = $this->checkListWithField(CheckList::ACCESS_FRIENDS);
        $checkList->setOwner($owner);
        $this->em->persist((new Friendship($owner, $friend))->accept());
        $this->em->persist(new Friendship($owner, $stranger)); // only a request: not friends yet
        $this->em->flush();
        $fillUrl = '/checklists/' . $checkList->getId() . '/fill';

        $this->client->request('GET', $fillUrl);
        $this->assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'), 'anonymous: log in first');

        $this->loginAs($stranger);
        $this->client->request('GET', $fillUrl);
        $this->assertResponseStatusCodeSame(404);
        $crawler = $this->client->request('GET', '/checklists');
        $this->assertStringNotContainsString($checkList->getName(), $crawler->filter('main')->text());

        $this->loginAs($friend);
        $crawler = $this->client->request('GET', '/checklists');
        $this->assertStringContainsString($checkList->getName(), $crawler->filter('main')->text());
        $form = $this->client->request('GET', $fillUrl)->filter('form[name="check_list_item"]')->form();
        $form['check_list_item[field_naam]'] = 'Vriend';
        $this->client->submit($form);
        $this->assertResponseRedirects('/checklists/' . $checkList->getId() . '/mine');

        $this->loginAs($owner);
        $crawler = $this->client->request('GET', '/checklists');
        $this->assertStringContainsString($checkList->getName(), $crawler->filter('main')->text(), 'the owner sees it too');
    }

    public function testUserCanViewAndEditOwnSubmissions(): void
    {
        $checkList = $this->checkListWithField(CheckList::ACCESS_USER);
        $user = $this->createUser();
        $this->loginAs($user);

        $form = $this->client->request('GET', '/checklists/' . $checkList->getId() . '/fill')->filter('form[name="check_list_item"]')->form();
        $form['check_list_item[field_naam]'] = 'Jan';
        $this->client->submit($form);
        $this->assertResponseRedirects('/checklists/' . $checkList->getId() . '/mine');

        $itemId = (int) $this->connection->fetchOne('SELECT id FROM checklist_items WHERE check_list_id = ? AND created_by = ?', [$checkList->getId(), $user->getId()]);
        $this->assertGreaterThan(0, $itemId);
        $this->client->request('GET', '/checklists/' . $checkList->getId() . '/mine');
        $this->assertSelectorTextContains('main table', 'Inzending #' . $itemId);

        $form = $this->client->request('GET', '/checklists/item/' . $itemId . '/edit')->filter('form[name="check_list_item"]')->form();
        $form['check_list_item[field_naam]'] = 'Jan Jansen';
        $this->client->submit($form);
        $this->assertResponseRedirects('/checklists/item/' . $itemId);
        $this->assertSame('Jan Jansen', json_decode((string) $this->connection->fetchOne('SELECT item_content FROM checklist_items WHERE id = ?', [$itemId]), true)['field_naam']);
        $this->assertEquals($user->getId(), $this->connection->fetchOne('SELECT changed_by FROM checklist_items WHERE id = ?', [$itemId]));
    }

    public function testUserCannotSeeSomeoneElsesOrAnonymousSubmissions(): void
    {
        $checkList = $this->checkListWithField(CheckList::ACCESS_PUBLIC);
        $owner = $this->createUser();
        $ownersItem = $this->createItem($checkList, ['field_naam' => 'Eigenaar'], $owner);
        $anonymousItem = $this->createItem($checkList, ['field_naam' => 'Anoniem']);

        $this->loginAs($this->createUser());
        foreach ([$ownersItem, $anonymousItem] as $item) {
            $this->client->request('GET', '/checklists/item/' . $item->getId());
            $this->assertResponseStatusCodeSame(404);
            $this->client->request('GET', '/checklists/item/' . $item->getId() . '/edit');
            $this->assertResponseStatusCodeSame(404);
        }
    }

    public function testFilledInHoneypotIsTreatedAsSpam(): void
    {
        $checkList = $this->checkListWithField(CheckList::ACCESS_PUBLIC);

        $form = $this->client->request('GET', '/checklists/' . $checkList->getId() . '/fill')->filter('form[name="check_list_item"]')->form();
        $form['check_list_item[field_naam]'] = 'Bot';
        $form['check_list_item[website]'] = 'http://spam.example';
        $this->client->submit($form);

        // Looks like it worked, but nothing is saved
        $this->assertResponseRedirects('/checklists/' . $checkList->getId() . '/thanks');
        $this->assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM checklist_items WHERE check_list_id = ?', [$checkList->getId()]));
    }

    public function testSubmissionsAreRateLimited(): void
    {
        $checkList = $this->checkListWithField(CheckList::ACCESS_PUBLIC);

        for ($i = 1; $i <= 6; ++$i) {
            $form = $this->client->request('GET', '/checklists/' . $checkList->getId() . '/fill')->filter('form[name="check_list_item"]')->form();
            $form['check_list_item[field_naam]'] = 'Inzending ' . $i;
            $this->client->submit($form);
        }

        // 5 per 10 minutes: the 6th is refused
        $this->assertResponseStatusCodeSame(429);
        $this->assertSelectorTextContains('.alert-warning', 'te vaak');
        $this->assertSame(5, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM checklist_items WHERE check_list_id = ?', [$checkList->getId()]));
    }

    public function testUploadedFileIsOnlyAvailableToItsOwnerAndAdmins(): void
    {
        $checkList = $this->createCheckList('Bestanden', CheckList::ACCESS_USER);
        $this->createField($checkList, 'Bijlage', 'file');
        $owner = $this->createUser();
        $stored = 'uploads/checklist/' . $checkList->getId() . '/foto.png';
        $file = static::getContainer()->getParameter('kernel.project_dir') . '/var/storage/' . $stored;
        @mkdir(dirname($file), 0777, true);
        // A real 1x1 PNG, so it is recognised as an image
        file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
        $item = $this->createItem($checkList, ['field_bijlage' => $stored], $owner);
        $url = '/checklists/item/' . $item->getId() . '/file/bijlage';

        try {
            $this->client->request('GET', $url);
            $this->assertResponseRedirects(message: 'anonymous visitors have to log in');

            $this->loginAs($this->createUser());
            $this->client->request('GET', $url);
            $this->assertResponseStatusCodeSame(404, 'another user');

            $this->loginAs($owner);
            $this->client->request('GET', $url);
            $this->assertResponseIsSuccessful();
            $this->assertStringStartsWith('inline;', (string) $this->client->getResponse()->headers->get('Content-Disposition'), 'images open in the browser');
            $this->assertSame('nosniff', $this->client->getResponse()->headers->get('X-Content-Type-Options'));

            $this->loginAs($this->createUser('ROLE_ADMIN'));
            $this->client->request('GET', $url);
            $this->assertResponseIsSuccessful();
        } finally {
            @unlink($file);
            @rmdir(dirname($file));
            @rmdir(dirname($file, 2));
            @rmdir(dirname($file, 3));
        }
    }

    private function checkListWithField(string $access): CheckList
    {
        $checkList = $this->createCheckList('Openbaar', $access);
        $this->createField($checkList, 'Naam', 'text');

        return $checkList;
    }
}
