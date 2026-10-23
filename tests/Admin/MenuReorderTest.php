<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Menu;
use App\Entity\MenuItem;
use App\Entity\Role;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Dragging menu items in the admin saves the whole order of the lists involved, also into and out of a submenu.
 */
class MenuReorderTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->em->getConnection();
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    public function testWholeListIsNumberedAndItemsMoveBetweenLevels(): void
    {
        $menu = $this->menu();
        // Positions as they were in the real menu: duplicates
        [$home, $blog, $events, $games] = [$this->item($menu, 'Home', 1), $this->item($menu, 'Blog', 4), $this->item($menu, 'Events', 3), $this->item($menu, 'Games', 4)];
        $token = $this->openMenuPage($menu);

        // Blog dragged into a submenu of Events; the top level closes the gap
        $this->reorder($menu, $token, [
            ['parentId' => null, 'items' => [$home->getId(), $events->getId(), $games->getId()]],
            ['parentId' => $events->getId(), 'items' => [$blog->getId()]],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertOrder([$home, $events, $games], null);
        $this->assertOrder([$blog], $events);

        // And back out, to the top
        $this->reorder($menu, $token, [
            ['parentId' => $events->getId(), 'items' => []],
            ['parentId' => null, 'items' => [$blog->getId(), $home->getId(), $events->getId(), $games->getId()]],
        ]);
        $this->assertOrder([$blog, $home, $events, $games], null);
    }

    public function testRefusesBadRequests(): void
    {
        $menu = $this->menu();
        $parent = $this->item($menu, 'Parent', 1);
        $child = $this->item($menu, 'Child', 1, $parent);
        $foreign = $this->item($this->menu(), 'Other menu', 1);
        $token = $this->openMenuPage($menu);

        $this->reorder($menu, 'wrong-token', [['parentId' => null, 'items' => [$parent->getId()]]]);
        $this->assertResponseStatusCodeSame(403);

        $this->reorder($menu, $token, [['parentId' => null, 'items' => [$foreign->getId()]]]);
        $this->assertResponseStatusCodeSame(400, 'an item of another menu');

        $this->reorder($menu, $token, [['parentId' => $child->getId(), 'items' => [$parent->getId()]]]);
        $this->assertResponseStatusCodeSame(400, 'a parent into its own submenu');
        $this->assertNull($this->connection->fetchOne('SELECT parent_id FROM menu_items WHERE id = ?', [$parent->getId()]));
    }

    /** @param list<array{parentId: ?int, items: list<?int>}> $lists */
    private function reorder(Menu $menu, string $token, array $lists): void
    {
        $this->client->request('POST', '/admin/menu/' . $menu->getId() . '/reorder', [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode(['_token' => $token, 'lists' => $lists]));
    }

    /** @param list<MenuItem> $expected */
    private function assertOrder(array $expected, ?MenuItem $parent): void
    {
        foreach ($expected as $position => $item) {
            $row = $this->connection->fetchAssociative('SELECT sort_order, parent_id FROM menu_items WHERE id = ?', [$item->getId()]);
            $this->assertSame($position + 1, (int) $row['sort_order'], $item->getLabel());
            $this->assertSame($parent?->getId(), $row['parent_id'] !== null ? (int) $row['parent_id'] : null, $item->getLabel());
        }
    }

    /** Logs in as admin and returns the CSRF token the page gives the script */
    private function openMenuPage(Menu $menu): string
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_ADMIN']) ?? (new Role())->setName('ROLE_ADMIN')->setDescription('ROLE_ADMIN');
        $this->em->persist($role);
        $user = (new User())->setEmail(uniqid('menu-') . '@example.com')->setFullName('Menu test')->setPassword('not-used');
        $user->addRole($role);
        $this->em->persist($user);
        $this->em->flush();
        $this->client->loginUser($user, 'primary_auth');

        $crawler = $this->client->request('GET', '/admin/menu/' . $menu->getId());
        $this->assertResponseIsSuccessful();

        return (string) $crawler->filter('script[data-reorder-url]')->attr('data-csrf-token');
    }

    private function menu(): Menu
    {
        $menu = (new Menu())->setName('test-' . uniqid());
        $this->em->persist($menu);
        $this->em->flush();

        return $menu;
    }

    private function item(Menu $menu, string $label, int $sortOrder, ?MenuItem $parent = null): MenuItem
    {
        $item = (new MenuItem())->setMenu($menu)->setLabel($label)->setLink('/' . strtolower($label))->setSortOrder($sortOrder)->setParent($parent);
        $menu->addMenuItem($item);
        $this->em->persist($item);
        $this->em->flush();

        return $item;
    }
}
