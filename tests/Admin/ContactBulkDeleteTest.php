<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Contact;
use App\Entity\Role;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * In the admin the contact messages can be ticked and deleted at once, or all of them in one go.
 */
class ContactBulkDeleteTest extends WebTestCase
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
        $this->connection->executeStatement('DELETE FROM contact');
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    public function testTickedMessagesAreDeleted(): void
    {
        $this->contact('First');
        $this->contact('Second');
        $this->contact('Third');
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/contact/');
        $form = $crawler->filter('#contactBulkForm')->form();
        $form['ids'][0]->tick();
        $form['ids'][1]->tick();
        $ticked = [(int) $form['ids'][0]->getValue(), (int) $form['ids'][1]->getValue()];
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/contact/');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', '2 contactformulieren verwijderd');
        $left = array_map('intval', $this->connection->fetchFirstColumn('SELECT id FROM contact'));
        $this->assertCount(1, $left);
        $this->assertNotContains($left[0], $ticked);
    }

    public function testAllMessagesAreDeletedAlsoOnOtherPages(): void
    {
        for ($i = 1; $i <= 25; ++$i) {
            $this->contact('Message ' . $i);
        }
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/contact/');
        $this->assertCount(20, $crawler->filter('.contact-checkbox'));
        $this->client->submit($crawler->filter('#contactBulkForm')->form(['all' => '1']));

        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', '25 contactformulieren verwijderd');
        $this->assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM contact'));
    }

    public function testNothingTickedDeletesNothing(): void
    {
        $this->contact('Stays');
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/contact/');
        $this->client->submit($crawler->filter('#contactBulkForm')->form());

        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'Selecteer eerst');
        $this->assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM contact'));
    }

    public function testWrongTokenDeletesNothing(): void
    {
        $this->contact('Stays');
        $this->loginAsAdmin();

        $this->client->request('POST', '/admin/contact/bulk-delete', ['_token' => 'wrong', 'all' => '1']);

        $this->assertResponseRedirects('/admin/contact/');
        $this->assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM contact'));
    }

    private function contact(string $subject): Contact
    {
        $contact = (new Contact())->setName('Visitor')->setEmail('visitor@example.test')->setSubject($subject)->setMessage('A message');
        $this->em->persist($contact);
        $this->em->flush();

        return $contact;
    }

    private function loginAsAdmin(): void
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_ADMIN']) ?? (new Role())->setName('ROLE_ADMIN')->setDescription('ROLE_ADMIN');
        $this->em->persist($role);
        $user = (new User())->setEmail(uniqid('bulk-') . '@example.com')->setFullName('Admin')->setPassword('not-used')->addRole($role);
        $this->em->persist($user);
        $this->em->flush();
        $this->client->loginUser($user, 'primary_auth');
    }
}
