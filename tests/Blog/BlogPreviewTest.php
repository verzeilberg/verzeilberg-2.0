<?php

declare(strict_types=1);

namespace App\Tests\Blog;

use App\Entity\BlogPost;
use App\Entity\Role;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\UriSigner;

/**
 * A post that isn't online can be read by whoever gets the signed preview link from the admin edit page.
 */
class BlogPreviewTest extends WebTestCase
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

    public function testDraftIsReadableWithTheLinkFromTheAdmin(): void
    {
        $post = $this->post(BlogPost::STATUS_DRAFT);
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/blog/' . $post->getId() . '/edit');
        $this->assertResponseIsSuccessful();
        $link = (string) $crawler->filter('#previewUrl')->attr('value');
        $this->assertStringContainsString('/blog/preview/' . $post->getId(), $link);

        $this->client->request('GET', '/logout');
        $this->client->request('GET', $link);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.page-title', $post->getTitle());
        $this->assertSelectorTextContains('.alert-warning', 'Voorbeeld');
        $this->assertSelectorNotExists('#comments', 'no comments on a preview');
        $this->assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');

        $this->client->request('GET', '/blog/' . $post->getSlug());
        $this->assertResponseStatusCodeSame(404, 'without the link the draft stays hidden');
    }

    public function testLinkMustBeSignedAndNotExpired(): void
    {
        $post = $this->post(BlogPost::STATUS_DRAFT);
        $signer = static::getContainer()->get(UriSigner::class);
        $url = 'http://localhost/blog/preview/' . $post->getId();

        $this->client->request('GET', '/blog/preview/' . $post->getId());
        $this->assertResponseStatusCodeSame(404, 'not signed');

        $this->client->request('GET', str_replace('/preview/' . $post->getId(), '/preview/' . ($post->getId() + 1), $signer->sign($url, new \DateTimeImmutable('+1 day'))));
        $this->assertResponseStatusCodeSame(404, 'signed for another post');

        $this->client->request('GET', $signer->sign($url, new \DateTimeImmutable('-1 minute')));
        $this->assertResponseStatusCodeSame(404, 'expired');
    }

    public function testOnlinePostHasNoPreviewLinkAndItsOldLinkRedirects(): void
    {
        $post = $this->post(BlogPost::STATUS_ONLINE);
        $this->loginAsAdmin();

        $this->client->request('GET', '/admin/blog/' . $post->getId() . '/edit');
        $this->assertSelectorNotExists('#previewUrl');

        $signed = static::getContainer()->get(UriSigner::class)->sign('http://localhost/blog/preview/' . $post->getId(), new \DateTimeImmutable('+1 day'));
        $this->client->request('GET', $signed);
        $this->assertResponseRedirects('/blog/' . $post->getSlug());
    }

    private function post(int $status): BlogPost
    {
        $post = (new BlogPost())
            ->setTitle('Preview test ' . uniqid())
            ->setSlug('preview-test-' . uniqid())
            ->setIntroText('Intro')
            ->setContent('<p>Content</p>')
            ->setStatus($status)
            ->setDateOnline(new \DateTimeImmutable('-1 day'));
        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }

    private function loginAsAdmin(): void
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_ADMIN']) ?? (new Role())->setName('ROLE_ADMIN')->setDescription('ROLE_ADMIN');
        $this->em->persist($role);
        $user = (new User())->setEmail(uniqid('preview-') . '@example.com')->setFullName('Preview test')->setPassword('not-used');
        $user->addRole($role);
        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user, 'primary_auth');
    }
}
