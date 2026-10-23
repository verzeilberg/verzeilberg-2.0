<?php

declare(strict_types=1);

namespace App\Tests\User;

use App\Entity\User;
use App\Entity\UserDetails;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The user directory, the public / private switch and the edit profile page.
 */
class UserProfileTest extends WebTestCase
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

    public function testDirectoryListsAndSearchesOnlyPublicUsers(): void
    {
        $alice = $this->user('Alice Zeewind', public: true, location: 'Harlingen');
        $bob = $this->user('Bob Zeewind', public: false);

        $this->client->request('GET', '/users?q=zeewind');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.card-title', 'Alice Zeewind');
        $this->assertSelectorTextNotContains('main', 'Bob Zeewind');

        $this->client->request('GET', '/users?q=harlingen');
        $this->assertSelectorTextContains('.card-title', 'Alice Zeewind', 'search on location');

        $this->client->request('GET', '/users/' . $alice->getId());
        $this->assertResponseIsSuccessful();
        $this->client->request('GET', '/users/' . $bob->getId());
        $this->assertResponseStatusCodeSame(404, 'a private page is hidden from others');

        $this->client->loginUser($bob, 'primary_auth');
        $this->client->request('GET', '/users/' . $bob->getId());
        $this->assertResponseIsSuccessful('the owner sees their own private page');
        $this->assertSelectorExists('.alert-warning');
    }

    public function testUserMakesTheirPagePublic(): void
    {
        $user = $this->user('Carla Zeewind', public: false);
        $this->client->loginUser($user, 'primary_auth');

        $crawler = $this->client->request('GET', '/profile');
        $this->client->submit($crawler->filter('form[action="/profile/visibility"]')->form(['profilePublic' => '1']));
        $this->assertResponseRedirects('/profile');

        $this->assertTrue($this->reload($user)->isProfilePublic());
    }

    public function testEditProfile(): void
    {
        $user = $this->user('Dirk Zeewind', public: true, withDetails: false);
        $this->client->loginUser($user, 'primary_auth');

        $crawler = $this->client->request('GET', '/profile/edit');
        $this->assertResponseIsSuccessful();
        $this->assertSame('Dirk', $crawler->filter('#profile_form_firstName')->attr('value'), 'no details yet: name split from the full name');

        $this->client->submit($crawler->selectButton('Profiel opslaan')->form([
            'profile_form[website]' => 'not a url',
        ]));
        $this->assertResponseStatusCodeSame(422);

        // A 1×1 PNG
        $image = tempnam(sys_get_temp_dir(), 'avatar');
        file_put_contents($image, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        $form = $crawler->selectButton('Profiel opslaan')->form([
            'profile_form[firstName]' => 'Dirk',
            'profile_form[lastName]' => 'van Zeewind',
            'profile_form[catchLine]' => 'Always on the water',
            'profile_form[location]' => 'Makkum',
            'profile_form[website]' => 'https://example.com',
            'profile_form[socialLinks][github]' => 'https://github.com/dirk',
        ]);
        $form['profile_form[avatarFile]']->upload($image);
        $this->client->submit($form);
        $this->assertResponseRedirects('/profile');

        $user = $this->reload($user);
        $details = $user->getDetails();
        $this->assertSame('Dirk van Zeewind', $user->getFullName());
        $this->assertSame('Makkum', $details->getLocation());
        $this->assertSame(['github' => 'https://github.com/dirk'], $details->getSocialLinks());
        $this->assertStringStartsWith('uploads/avatars/' . $user->getId() . '-', $details->getAvatar());

        $avatarPath = static::getContainer()->getParameter('app.uploads_dir') . '/avatars/' . basename($details->getAvatar());
        $this->assertFileExists($avatarPath);

        // Removing the avatar deletes the file
        $crawler = $this->client->request('GET', '/profile/edit');
        $this->client->submit($crawler->selectButton('Profiel opslaan')->form(['profile_form[removeAvatar]' => '1']));
        $this->assertNull($this->reload($user)->getDetails()->getAvatar());
        $this->assertFileDoesNotExist($avatarPath);
    }

    public function testChangePassword(): void
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = $this->user('Eva Zeewind', public: false);
        $user->setPassword($hasher->hashPassword($user, 'Old-password1'));
        $this->em->flush();
        $this->client->loginUser($user, 'primary_auth');

        $crawler = $this->client->request('GET', '/profile/password');
        $this->assertResponseIsSuccessful();
        $submit = fn (string $current, string $new, string $confirm) => $this->client->submit($crawler->selectButton('Wachtwoord wijzigen')->form([
            'change_password_form[currentPassword]' => $current,
            'change_password_form[plainPassword]' => $new,
            'change_password_form[confirmPassword]' => $confirm,
        ]));

        $submit('wrong', 'New-password2', 'New-password2');
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('main', 'Dit is niet je huidige wachtwoord');

        $submit('Old-password1', 'New-password2', 'Other-password3');
        $this->assertSelectorTextContains('main', 'De wachtwoorden moeten gelijk zijn.');

        $submit('Old-password1', 'Old-password1', 'Old-password1');
        $this->assertSelectorTextContains('main', 'moet anders zijn dan je huidige');

        $submit('Old-password1', 'New-password2', 'New-password2');
        $this->assertResponseRedirects('/profile');
        $this->assertTrue($hasher->isPasswordValid($this->reload($user), 'New-password2'));

        $this->client->request('GET', '/profile');
        $this->assertResponseIsSuccessful('still logged in after the change');
    }

    private function reload(User $user): User
    {
        $this->em->clear();
        return $this->em->find(User::class, $user->getId());
    }

    private function user(string $name, bool $public, ?string $location = null, bool $withDetails = true): User
    {
        $user = (new User())
            ->setEmail(strtolower(str_replace(' ', '.', $name)) . '@example.test')
            ->setFullName($name)
            ->setPassword('unused')
            ->setProfilePublic($public);

        if ($withDetails) {
            [$first, $last] = explode(' ', $name, 2);
            $user->setDetails((new UserDetails())->setUser($user)->setFirstName($first)->setLastName($last)->setLocation($location));
        }

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
