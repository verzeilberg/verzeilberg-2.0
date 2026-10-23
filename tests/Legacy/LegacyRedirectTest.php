<?php

declare(strict_types=1);

namespace App\Tests\Legacy;

use App\Entity\BlogPost;
use App\Entity\Category;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * URLs of the old site redirect permanently to the new pages.
 */
class LegacyRedirectTest extends WebTestCase
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

    public function testOldBlogUrls(): void
    {
        $category = (new Category())->setName('Legacy')->setSlug('legacy-' . uniqid())->setEnabled(true);
        $post = (new BlogPost())->setTitle('Legacy post')->setSlug('legacy-post-' . uniqid())->setIntroText('Intro')->setContent('<p>Content</p>')
            ->setStatus(BlogPost::STATUS_ONLINE)->setDateOnline(new \DateTimeImmutable('-1 day'));
        $this->em->persist($category);
        $this->em->persist($post);
        $this->em->flush();

        $this->client->request('GET', '/blog/detail/' . $post->getSlug());
        $this->assertResponseRedirects('/blog/' . $post->getSlug(), 301, 'an old slug that is still the same');

        $this->client->request('GET', '/blog/detail/no-such-post-' . uniqid());
        $this->assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/blog/category/' . $category->getId());
        $this->assertResponseRedirects('/blog/category/' . $category->getSlug(), 301);

        $this->client->request('GET', '/blog?page=2');
        $this->assertResponseRedirects('/blog/page/2', 301);
    }

    public function testOtherOldPages(): void
    {
        $this->client->request('GET', '/running-stats');
        $this->assertResponseRedirects('/running', 301);

        $this->client->request('GET', '/beheer/blog');
        $this->assertResponseRedirects('/admin/', 301);
    }

    public function testOldSlugTableIsValid(): void
    {
        $table = require static::getContainer()->getParameter('kernel.project_dir') . '/config/legacy_blog_slugs.php';

        $this->assertCount(58, $table);
        foreach ($table as $slug => $id) {
            $this->assertIsInt($id, (string) $slug);
        }
    }
}
