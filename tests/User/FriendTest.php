<?php

declare(strict_types=1);

namespace App\Tests\User;

use App\Entity\User;
use App\Entity\UserDetails;
use App\Repository\FriendshipRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Friend requests: send, accept, decline, cancel, unfriend, and friends seeing a private page.
 */
class FriendTest extends WebTestCase
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

    public function testRequestAcceptAndUnfriend(): void
    {
        $anna = $this->user('Anna Kaag', public: true);
        $bram = $this->user('Bram Kaag', public: false);

        // Bram (private) sends Anna (public) a request from her page
        $this->client->loginUser($bram, 'primary_auth');
        $crawler = $this->client->request('GET', '/users/' . $anna->getId());
        $this->client->submit($crawler->selectButton('Vriend toevoegen')->form());
        $this->assertResponseRedirects('/users/' . $anna->getId());
        $this->client->followRedirect();
        $this->assertSelectorTextContains('main', 'Vriendschapsverzoek verstuurd');
        $this->assertSelectorExists('button:contains("Verzoek intrekken")');

        // Anna sees the request (with the badge in the menu) and accepts it
        $this->client->loginUser($this->reload($anna), 'primary_auth');
        $crawler = $this->client->request('GET', '/friends');
        $this->assertSelectorTextContains('.dropdown-menu .badge', '1');
        $this->assertSelectorTextContains('main', 'Bram Kaag');
        $this->client->submit($crawler->selectButton('Accepteren')->form());
        $this->assertResponseRedirects('/friends');

        $this->assertTrue($this->friendships()->areFriends($this->reload($anna), $this->reload($bram)));

        // As a friend, Anna can open Bram's private page
        $this->client->request('GET', '/users/' . $bram->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('button:contains("Ontvrienden")');

        // Unfriending closes the private page again, and returns to the friends page
        $crawler = $this->client->request('GET', '/friends');
        $this->client->submit($crawler->selectButton('Ontvrienden')->form());
        $this->assertResponseRedirects('/friends');
        $this->client->request('GET', '/users/' . $bram->getId());
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeclineAndCancel(): void
    {
        $anna = $this->user('Anna Kaag', public: true);
        $bram = $this->user('Bram Kaag', public: true);

        $this->client->loginUser($anna, 'primary_auth');
        $crawler = $this->client->request('GET', '/users/' . $bram->getId());
        $this->client->submit($crawler->selectButton('Vriend toevoegen')->form());
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Verzoek intrekken')->form());
        $this->assertNull($this->friendships()->findBetween($this->reload($anna), $this->reload($bram)), 'cancelled');

        $crawler = $this->client->request('GET', '/users/' . $bram->getId());
        $this->client->submit($crawler->selectButton('Vriend toevoegen')->form());

        $this->client->loginUser($this->reload($bram), 'primary_auth');
        $crawler = $this->client->request('GET', '/friends');
        $this->client->submit($crawler->selectButton('Weigeren')->form());
        $this->assertNull($this->friendships()->findBetween($this->reload($anna), $this->reload($bram)), 'declined');
    }

    public function testCantRequestAPrivatePageOrWithoutToken(): void
    {
        $anna = $this->user('Anna Kaag', public: true);
        $bram = $this->user('Bram Kaag', public: false);
        $this->client->loginUser($anna, 'primary_auth');

        $this->client->request('GET', '/friends'); // starts the session that holds the token
        $this->client->request('POST', '/friends/' . $bram->getId() . '/add', ['_token' => 'wrong']);
        $this->assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/friends/' . $anna->getId() . '/add', ['_token' => 'any']);
        $this->assertResponseStatusCodeSame(404, 'not yourself');

        $this->client->request('GET', '/users');
        $this->assertSelectorTextNotContains('main', 'Bram Kaag', 'a private user can not be found to befriend');
        $this->assertNull($this->friendships()->findBetween($this->reload($anna), $this->reload($bram)));
    }

    private function friendships(): FriendshipRepository
    {
        return static::getContainer()->get(FriendshipRepository::class);
    }

    private function reload(User $user): User
    {
        $this->em->clear();
        return $this->em->find(User::class, $user->getId());
    }

    private function user(string $name, bool $public): User
    {
        [$first, $last] = explode(' ', $name, 2);
        $user = (new User())
            ->setEmail(strtolower(str_replace(' ', '.', $name)) . '@example.test')
            ->setFullName($name)
            ->setPassword('unused')
            ->setProfilePublic($public);
        $user->setDetails((new UserDetails())->setUser($user)->setFirstName($first)->setLastName($last));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
