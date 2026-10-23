<?php

namespace App\Tests\Controller\Admin;

use App\Entity\BlogPost;
use App\Entity\Category;
use App\Entity\Role;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Every test runs inside a database transaction that is rolled back afterwards,
 * so tests don't depend on each other or on data left in the test database.
 */
class BlogControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Connection $connection;
    private User $adminUser;
    private Category $testCategory;
    /** Highest blog_image/blog_file ids before the test, to remove the files the test uploads */
    private int $maxImageId = 0;
    private int $maxFileId = 0;
    /** @var string[] temporary files created by the test */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // One kernel (so one database connection) for all requests of a test, so the rollback covers them
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->em->getConnection();
        $this->connection->beginTransaction();

        $this->maxImageId = (int) $this->connection->fetchOne('SELECT COALESCE(MAX(id), 0) FROM blog_image');
        $this->maxFileId = (int) $this->connection->fetchOne('SELECT COALESCE(MAX(id), 0) FROM blog_file');

        $this->adminUser = $this->createUser('ROLE_ADMIN');

        $this->testCategory = new Category();
        $this->testCategory->setName('Test Category');
        $this->testCategory->setSlug('test-category-' . uniqid());
        $this->testCategory->setEnabled(true);
        $this->em->persist($this->testCategory);
        $this->em->flush();

        // The firewall uses "context: primary_auth"
        $this->client->loginUser($this->adminUser, 'primary_auth');
    }

    protected function tearDown(): void
    {
        // Remove the files uploaded during the test before their database rows are rolled back
        foreach (['blog_image' => $this->maxImageId, 'blog_file' => $this->maxFileId] as $table => $maxId) {
            foreach ($this->connection->fetchAllAssociative("SELECT path, filename FROM $table WHERE id > ?", [$maxId]) as $row) {
                @unlink($row['path'] . '/' . $row['filename']);
            }
        }
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function createUser(string $roleName): User
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => $roleName]);
        if (!$role) {
            $role = new Role();
            $role->setName($roleName);
            $role->setDescription($roleName);
            $this->em->persist($role);
        }

        $user = new User();
        $user->setEmail(uniqid('blog-test-') . '@example.com');
        $user->setFullName('Test ' . $roleName);
        $user->setPassword('not-used');
        $user->addRole($role);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createTestBlogPost(array $overrides = []): BlogPost
    {
        $post = new BlogPost();
        $post->setTitle($overrides['title'] ?? 'Test Blog Post');
        $post->setSlug($overrides['slug'] ?? 'test-blog-post-' . uniqid());
        $post->setIntroText($overrides['introText'] ?? 'Test introduction');
        $post->setContent($overrides['content'] ?? 'Test content');
        $post->setStatus($overrides['status'] ?? BlogPost::STATUS_DRAFT);
        $post->addCategory($this->testCategory);

        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }

    /** The entity manager is cleared after every request, so fetch a fresh copy from the database */
    private function reload(BlogPost $post): BlogPost
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return $em->getRepository(BlogPost::class)->find($post->getId());
    }

    /** A real (1x1 pixel) PNG image to upload */
    private function createImageUpload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'blogimg');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
        $this->tempFiles[] = $path;

        return new UploadedFile($path, 'pixel.png', 'image/png', null, true);
    }

    private function createTextUpload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'blogfile');
        file_put_contents($path, 'test file content');
        $this->tempFiles[] = $path;

        return new UploadedFile($path, 'test.txt', 'text/plain', null, true);
    }

    /** The CSRF token a delete button on the edit page carries, as the browser would send it */
    private function tokenFromEditPage(BlogPost $post, string $selector): string
    {
        $crawler = $this->client->request('GET', '/admin/blog/' . $post->getId() . '/edit');

        return (string) $crawler->filter($selector)->attr('data-csrf-token');
    }

    private function postAjax(string $url, array $parameters = [], array $files = []): array
    {
        $this->client->request('POST', $url, $parameters, $files, ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        return json_decode($this->client->getResponse()->getContent(), true) ?? [];
    }

    public function testIndexPageLoads(): void
    {
        $this->createTestBlogPost();

        $this->client->request('GET', '/admin/blog/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
        $this->assertSelectorExists('table tbody tr');
    }

    public function testIndexFiltersByStatus(): void
    {
        $this->createTestBlogPost(['title' => 'Draft Post', 'status' => BlogPost::STATUS_DRAFT]);
        $this->createTestBlogPost(['title' => 'Online Post', 'status' => BlogPost::STATUS_ONLINE]);

        $this->client->request('GET', '/admin/blog/?status=draft');
        $this->assertResponseIsSuccessful();

        $html = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Draft Post', $html);
        $this->assertStringNotContainsString('Online Post', $html);
    }

    public function testIndexFiltersByCategory(): void
    {
        $this->client->request('GET', '/admin/blog/?category=' . $this->testCategory->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testNewBlogPostPageLoads(): void
    {
        $this->client->request('GET', '/admin/blog/new');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="blog_post[title]"]');
        $this->assertSelectorExists('textarea[name="blog_post[content]"]');
    }

    public function testCreateBlogPost(): void
    {
        $crawler = $this->client->request('GET', '/admin/blog/new');
        $this->assertResponseIsSuccessful();

        $title = 'New Test Post ' . uniqid();
        $form = $crawler->selectButton('Opslaan als concept')->form([
            'blog_post[title]' => $title,
            'blog_post[introText]' => 'Test intro text',
            'blog_post[content]' => 'Test content body',
            'blog_post[status]' => BlogPost::STATUS_DRAFT,
        ]);
        $categories = $form['blog_post[categories]'];
        $this->assertInstanceOf(ChoiceFormField::class, $categories);
        $categories->select([(string) $this->testCategory->getId()]);

        $this->client->submit($form);
        // After creating, the post opens in the edit page
        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Bericht aangemaakt.', $this->client->getResponse()->getContent());

        $newPost = $this->em->getRepository(BlogPost::class)->findOneBy(['title' => $title]);
        $this->assertNotNull($newPost);
        $this->assertEquals(strtolower(str_replace(' ', '-', $title)), $newPost->getSlug());
        $this->assertTrue($newPost->getCategories()->exists(fn ($key, Category $category) => $category->getId() === $this->testCategory->getId()));
    }

    public function testCreateBlogPostWithOnlineStatus(): void
    {
        $crawler = $this->client->request('GET', '/admin/blog/new');
        $this->assertResponseIsSuccessful();

        $title = 'Online Test Post ' . uniqid();
        $form = $crawler->selectButton('Opslaan als concept')->form([
            'blog_post[title]' => $title,
            'blog_post[introText]' => 'Test intro',
            'blog_post[content]' => 'Test content',
            'blog_post[status]' => BlogPost::STATUS_ONLINE,
        ]);

        $this->client->submit($form);
        $this->assertResponseRedirects();

        $newPost = $this->em->getRepository(BlogPost::class)->findOneBy(['title' => $title]);
        $this->assertNotNull($newPost);
        $this->assertEquals(BlogPost::STATUS_ONLINE, $newPost->getStatus());
    }

    public function testShowBlogPost(): void
    {
        $post = $this->createTestBlogPost();

        $this->client->request('GET', '/admin/blog/' . $post->getId());
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString($post->getTitle(), $this->client->getResponse()->getContent());
    }

    public function testEditBlogPostPageLoads(): void
    {
        $post = $this->createTestBlogPost();

        $this->client->request('GET', '/admin/blog/' . $post->getId() . '/edit');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="blog_post[title]"][value="' . $post->getTitle() . '"]');
    }

    public function testUpdateBlogPost(): void
    {
        $post = $this->createTestBlogPost();
        $slug = $post->getSlug();

        $crawler = $this->client->request('GET', '/admin/blog/' . $post->getId() . '/edit');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Wijzigingen opslaan')->form([
            'blog_post[title]' => 'Updated Title',
            'blog_post[introText]' => 'Updated intro',
            'blog_post[content]' => 'Updated content',
            'blog_post[status]' => BlogPost::STATUS_ONLINE,
        ]);

        $this->client->submit($form);
        $this->assertResponseRedirects();

        $this->client->followRedirect();
        $this->assertStringContainsString('Bericht opgeslagen.', $this->client->getResponse()->getContent());

        $post = $this->reload($post);
        $this->assertEquals('Updated Title', $post->getTitle());
        $this->assertEquals(BlogPost::STATUS_ONLINE, $post->getStatus());
        $this->assertEquals($slug, $post->getSlug(), 'editing the title keeps the URL (slug)');
    }

    public function testDeleteBlogPost(): void
    {
        $post = $this->createTestBlogPost(['title' => 'Post to Delete']);

        // The index page's delete button carries the CSRF token the delete request needs
        $crawler = $this->client->request('GET', '/admin/blog/');
        $token = $crawler->filter('button.delete-post[data-post-id="' . $post->getId() . '"]')->attr('data-csrf-token');

        $response = $this->postAjax('/admin/blog/' . $post->getId(), ['_token' => $token]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['success']);
        $this->assertNull($this->connection->fetchOne('SELECT id FROM blog_post WHERE id = ?', [$post->getId()]) ?: null);
    }

    public function testDeleteBlogPostWithInvalidTokenFails(): void
    {
        $post = $this->createTestBlogPost();

        $response = $this->postAjax('/admin/blog/' . $post->getId(), ['_token' => 'invalid']);

        $this->assertResponseStatusCodeSame(403);
        $this->assertFalse($response['success']);
        $this->assertNotFalse($this->connection->fetchOne('SELECT id FROM blog_post WHERE id = ?', [$post->getId()]));
    }

    public function testChangeStatusToDraft(): void
    {
        $post = $this->createTestBlogPost(['status' => BlogPost::STATUS_ONLINE]);

        $response = $this->postAjax('/admin/blog/' . $post->getId() . '/status', ['status' => BlogPost::STATUS_DRAFT]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['success']);
        $this->assertEquals(BlogPost::STATUS_DRAFT, $response['status']);
        $this->assertEquals(BlogPost::STATUS_DRAFT, $this->reload($post)->getStatus());
    }

    public function testChangeStatusToOnline(): void
    {
        $post = $this->createTestBlogPost(['status' => BlogPost::STATUS_DRAFT]);

        $response = $this->postAjax('/admin/blog/' . $post->getId() . '/status', ['status' => BlogPost::STATUS_ONLINE]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['success']);
        $this->assertEquals(BlogPost::STATUS_ONLINE, $response['status']);
    }

    public function testChangeStatusToOffline(): void
    {
        $post = $this->createTestBlogPost(['status' => BlogPost::STATUS_ONLINE]);

        $response = $this->postAjax('/admin/blog/' . $post->getId() . '/status', ['status' => BlogPost::STATUS_OFFLINE]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['success']);
        $this->assertEquals(BlogPost::STATUS_OFFLINE, $response['status']);
    }

    public function testChangeStatusToArchived(): void
    {
        $post = $this->createTestBlogPost(['status' => BlogPost::STATUS_ONLINE]);

        $response = $this->postAjax('/admin/blog/' . $post->getId() . '/status', ['status' => BlogPost::STATUS_ARCHIVED]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['success']);
        $this->assertEquals(BlogPost::STATUS_ARCHIVED, $response['status']);
    }

    public function testChangeStatusInvalidStatusReturnsError(): void
    {
        $post = $this->createTestBlogPost();

        $response = $this->postAjax('/admin/blog/' . $post->getId() . '/status', ['status' => 999]);

        $this->assertResponseIsSuccessful();
        $this->assertFalse($response['success']);
        $this->assertEquals('Ongeldige status', $response['message']);
    }

    public function testImageUpload(): void
    {
        $post = $this->createTestBlogPost();

        $response = $this->postAjax('/admin/blog/' . $post->getId() . '/image/upload', [], ['image' => $this->createImageUpload()]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('image', $response);
        $this->assertArrayHasKey('id', $response['image']);
        $this->assertCount(1, $this->reload($post)->getImages());
    }

    public function testImageDelete(): void
    {
        $post = $this->createTestBlogPost();
        $imageId = $this->postAjax('/admin/blog/' . $post->getId() . '/image/upload', [], ['image' => $this->createImageUpload()])['image']['id'];
        $token = $this->tokenFromEditPage($post, '.delete-image[data-image-id="' . $imageId . '"]');

        $response = $this->postAjax('/admin/blog/' . $post->getId() . '/image/' . $imageId . '/delete', ['_token' => $token]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['success']);
        $this->assertCount(0, $this->reload($post)->getImages());
    }

    public function testFileUpload(): void
    {
        $post = $this->createTestBlogPost();

        $response = $this->postAjax('/admin/blog/' . $post->getId() . '/file/upload', [], ['file' => $this->createTextUpload()]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('file', $response);
        $this->assertCount(1, $this->reload($post)->getFiles());
    }

    public function testFileDelete(): void
    {
        $post = $this->createTestBlogPost();
        $fileId = $this->postAjax('/admin/blog/' . $post->getId() . '/file/upload', [], ['file' => $this->createTextUpload()])['file']['id'];
        $token = $this->tokenFromEditPage($post, '.delete-file[data-file-id="' . $fileId . '"]');

        $response = $this->postAjax('/admin/blog/' . $post->getId() . '/file/' . $fileId . '/delete', ['_token' => $token]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['success']);
        $this->assertCount(0, $this->reload($post)->getFiles());
    }

    public function testYouTubeAdd(): void
    {
        $post = $this->createTestBlogPost();

        $response = $this->postAjax('/admin/blog/' . $post->getId() . '/youtube/add', ['videoId' => 'dQw4w9WgXcQ', 'title' => 'Test Video']);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('youtube', $response);
        $this->assertEquals('dQw4w9WgXcQ', $response['youtube']['videoId']);
        $this->assertCount(1, $this->reload($post)->getYouTubes());
    }

    public function testYouTubeAddInvalidVideoId(): void
    {
        $post = $this->createTestBlogPost();

        $response = $this->postAjax('/admin/blog/' . $post->getId() . '/youtube/add', ['videoId' => '']);

        $this->assertResponseIsSuccessful();
        $this->assertFalse($response['success']);
        $this->assertEquals('Video-ID is verplicht', $response['message']);
    }

    public function testYouTubeDelete(): void
    {
        $post = $this->createTestBlogPost();
        $youtubeId = $this->postAjax('/admin/blog/' . $post->getId() . '/youtube/add', ['videoId' => 'dQw4w9WgXcQ', 'title' => 'Test Video'])['youtube']['id'];
        $token = $this->tokenFromEditPage($post, '.delete-youtube[data-youtube-id="' . $youtubeId . '"]');

        $response = $this->postAjax('/admin/blog/' . $post->getId() . '/youtube/' . $youtubeId . '/delete', ['_token' => $token]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['success']);
        $this->assertCount(0, $this->reload($post)->getYouTubes());
    }

    public function testReorderImages(): void
    {
        $post = $this->createTestBlogPost();
        $imageId1 = $this->postAjax('/admin/blog/' . $post->getId() . '/image/upload', [], ['image' => $this->createImageUpload()])['image']['id'];
        $imageId2 = $this->postAjax('/admin/blog/' . $post->getId() . '/image/upload', [], ['image' => $this->createImageUpload()])['image']['id'];
        $this->assertCount(2, $this->reload($post)->getImages());

        // The new order is sent as a JSON body
        $this->client->request('POST', '/admin/blog/' . $post->getId() . '/images/reorder', [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'], json_encode([$imageId2, $imageId1]));

        $this->assertResponseIsSuccessful();
        $this->assertTrue(json_decode($this->client->getResponse()->getContent(), true)['success']);
        $order = $this->connection->fetchFirstColumn('SELECT id FROM blog_image WHERE id IN (?, ?) ORDER BY sort_order', [$imageId1, $imageId2]);
        $this->assertEquals([$imageId2, $imageId1], $order);
    }

    public function testAccessDeniedForNonAdmin(): void
    {
        $this->client->loginUser($this->createUser('ROLE_AUTHOR'), 'primary_auth');

        $this->client->request('GET', '/admin/blog/');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testUniqueSlugConstraint(): void
    {
        $title = 'Same Title ' . uniqid();
        $this->createTestBlogPost(['title' => $title, 'slug' => strtolower(str_replace(' ', '-', $title))]);

        $crawler = $this->client->request('GET', '/admin/blog/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Opslaan als concept')->form([
            'blog_post[title]' => $title,
            'blog_post[introText]' => 'Test intro',
            'blog_post[content]' => 'Test content',
            'blog_post[status]' => BlogPost::STATUS_DRAFT,
        ]);

        $this->client->submit($form);

        // Shown as a form error, not a database error
        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('Deze slug is al in gebruik', $this->client->getResponse()->getContent());
    }

    public function testBlogPostEntityMethods(): void
    {
        $post = new BlogPost();
        $post->setTitle('Test');
        $post->setSlug('test');
        $post->setIntroText('Intro');
        $post->setContent('Content');

        $this->assertTrue($post->isDraft());
        $this->assertFalse($post->isOnline());
        $this->assertFalse($post->isArchived());

        $post->setStatus(BlogPost::STATUS_ONLINE);
        $this->assertTrue($post->isOnline());
        $this->assertFalse($post->isDraft());

        $post->setStatus(BlogPost::STATUS_ARCHIVED);
        $this->assertTrue($post->isArchived());

        $this->assertEquals('Test', (string)$post);
    }

    public function testBlogPostScheduledPublishing(): void
    {
        $post = new BlogPost();
        $post->setStatus(BlogPost::STATUS_ONLINE);

        $this->assertFalse($post->isScheduledForPublishing());

        $post->setDateOnline(new \DateTimeImmutable('+1 day'));
        $this->assertTrue($post->isScheduledForPublishing());

        $post->setDateOnline(new \DateTimeImmutable('-1 day'));
        $this->assertFalse($post->isScheduledForPublishing());
    }

    public function testBlogPostScheduledUnpublishing(): void
    {
        $post = new BlogPost();
        $post->setStatus(BlogPost::STATUS_ONLINE);

        $this->assertFalse($post->isScheduledForUnpublishing());

        $post->setDateOffline(new \DateTimeImmutable('-1 day'));
        $this->assertTrue($post->isScheduledForUnpublishing());

        $post->setDateOffline(new \DateTimeImmutable('+1 day'));
        $this->assertFalse($post->isScheduledForUnpublishing());
    }

    public function testCategoriesRelationship(): void
    {
        $post = $this->createTestBlogPost();

        $this->assertCount(1, $post->getCategories());
        $this->assertTrue($post->getCategories()->contains($this->testCategory));

        $newCategory = new Category();
        $newCategory->setName('Another Category');
        $newCategory->setSlug('another-category-' . uniqid());
        $newCategory->setEnabled(true);
        $this->em->persist($newCategory);
        $this->em->flush();

        $post->addCategory($newCategory);
        $this->assertCount(2, $post->getCategories());

        $post->removeCategory($newCategory);
        $this->assertCount(1, $post->getCategories());
    }
}
