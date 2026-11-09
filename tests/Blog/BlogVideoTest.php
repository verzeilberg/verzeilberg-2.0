<?php

declare(strict_types=1);

namespace App\Tests\Blog;

use App\Entity\BlogPost;
use App\Entity\BlogVideo;
use App\Entity\Role;
use App\Entity\User;
use App\Service\BlogVideoStorage;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Videos with a blog post are only for logged in visitors: uploaded in chunks in the admin, played only through
 * the site's player, and invisible (no URL, no title) for anyone else.
 */
class BlogVideoTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Connection $connection;
    private BlogPost $post;
    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->em->getConnection();
        $this->connection->beginTransaction();

        $this->post = (new BlogPost())->setTitle('Video test')->setSlug('video-test-' . uniqid())->setIntroText('Intro')->setContent('<p>Text</p>')
            ->setStatus(BlogPost::STATUS_ONLINE)->setDateOnline(new \DateTimeImmutable('-1 minute'));
        $this->em->persist($this->post);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        foreach ($this->post->getVideos() as $video) {
            @unlink(static::getContainer()->get(BlogVideoStorage::class)->path($video));
        }
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    public function testAdminUploadsAVideoInChunks(): void
    {
        $this->client->loginUser($this->user(admin: true), 'primary_auth');
        $crawler = $this->client->request('GET', '/admin/blog/' . $this->post->getId() . '/edit');
        $form = $crawler->filter('#videoUploadForm');
        $token = $form->attr('data-csrf-token');

        $video = $this->fakeMp4(3000);
        $uploadId = bin2hex(random_bytes(16));
        $this->sendChunk($token, $uploadId, 0, substr($video, 0, 1000));
        $this->sendChunk($token, $uploadId, 1000, substr($video, 1000, 1000));
        // The same chunk again (a retry after a lost answer) is no problem
        $this->sendChunk($token, $uploadId, 1000, substr($video, 1000, 1000));
        $this->assertSame(2000, json_decode($this->client->getResponse()->getContent(), true)['received']);

        $this->sendChunk($token, $uploadId, 2000, substr($video, 2000), ['last' => '1', 'size' => '3000', 'name' => 'run.mp4', 'title' => 'Finish met [[Jan Jansen]]']);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success'], $data['message'] ?? '');
        $this->assertTrue($data['done']);

        $stored = $this->em->find(BlogVideo::class, $data['id']);
        $this->assertSame('video/mp4', $stored->getMimeType());
        $this->assertSame(3000, $stored->getFileSize());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}\.mp4$/', $stored->getFilename());
        $path = static::getContainer()->get(BlogVideoStorage::class)->path($stored);
        $this->assertSame($video, file_get_contents($path));

        // Deleting removes the file too
        $this->client->request('GET', '/admin/blog/' . $this->post->getId() . '/edit');
        $delete = $this->client->getCrawler()->filter('.delete-video');
        $this->client->request('POST', $delete->attr('data-delete-url'), ['_token' => $delete->attr('data-csrf-token')]);
        $this->assertTrue(json_decode($this->client->getResponse()->getContent(), true)['success']);
        $this->assertFileDoesNotExist($path);
    }

    public function testSomethingThatIsNotAVideoIsRefused(): void
    {
        $this->client->loginUser($this->user(admin: true), 'primary_auth');
        $crawler = $this->client->request('GET', '/admin/blog/' . $this->post->getId() . '/edit');
        $token = $crawler->filter('#videoUploadForm')->attr('data-csrf-token');

        $this->sendChunk($token, bin2hex(random_bytes(16)), 0, '<?php echo "hacked";', ['last' => '1', 'size' => '20', 'name' => 'x.mp4']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM blog_video WHERE blog_post_id = ?', [$this->post->getId()]));
    }

    public function testVisitorSeesNoVideoAndCanNotFetchIt(): void
    {
        $video = $this->storedVideo('Met [[Jan Jansen]]');

        $this->client->request('GET', '/blog/' . $this->post->getSlug());
        $html = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('alleen te zien is als je bent ingelogd', $html);
        $this->assertStringNotContainsString('/blog/video/', $html);
        $this->assertStringNotContainsString('<video', $html);
        $this->assertStringNotContainsString('Jansen', $html);

        $this->client->request('GET', '/blog/video/' . $video->getId(), [], [], ['HTTP_SEC_FETCH_DEST' => 'video']);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testLoggedInUserWatchesThroughThePlayerOnly(): void
    {
        $video = $this->storedVideo('Met [[Jan Jansen]]');
        $this->client->loginUser($this->user(), 'primary_auth');

        $crawler = $this->client->request('GET', '/blog/' . $this->post->getSlug());
        $player = $crawler->filter('video.protected-video');
        $this->assertCount(1, $player);
        $this->assertStringContainsString('nodownload', $player->attr('controlslist'));
        $this->assertSelectorTextContains('#videos', 'Met Jan Jansen');

        // The player gets it, also a part of it (seeking)
        $this->client->request('GET', $player->attr('src'), [], [], ['HTTP_SEC_FETCH_DEST' => 'video', 'HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_RANGE' => 'bytes=0-99']);
        $response = $this->client->getResponse();
        $this->assertSame(206, $response->getStatusCode());
        $this->assertSame('video/mp4', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));

        // Opening the URL in a tab, or from another site, is refused
        $this->client->request('GET', $player->attr('src'), [], [], ['HTTP_SEC_FETCH_DEST' => 'document']);
        $this->assertResponseStatusCodeSame(403);
        $this->client->request('GET', $player->attr('src'), [], [], ['HTTP_SEC_FETCH_DEST' => 'video', 'HTTP_SEC_FETCH_SITE' => 'cross-site']);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testVideoOfAPostThatIsNotOnlineIsNotServed(): void
    {
        $video = $this->storedVideo(null);
        $this->post->setStatus(BlogPost::STATUS_DRAFT);
        $this->em->flush();
        $this->client->loginUser($this->user(), 'primary_auth');

        $this->client->request('GET', '/blog/video/' . $video->getId(), [], [], ['HTTP_SEC_FETCH_DEST' => 'video']);

        $this->assertResponseStatusCodeSame(404);
    }

    /** @param array<string, string> $extra */
    private function sendChunk(string $token, string $uploadId, int $offset, string $bytes, array $extra = []): void
    {
        $file = tempnam(sys_get_temp_dir(), 'chunk');
        $this->tempFiles[] = $file;
        file_put_contents($file, $bytes);
        $this->client->request('POST', '/admin/blog/' . $this->post->getId() . '/video/upload',
            ['_token' => $token, 'uploadId' => $uploadId, 'offset' => (string) $offset] + $extra,
            ['chunk' => new UploadedFile($file, 'chunk', null, null, true)]
        );
    }

    private function storedVideo(?string $title): BlogVideo
    {
        $storage = static::getContainer()->get(BlogVideoStorage::class);
        $video = (new BlogVideo($this->post, bin2hex(random_bytes(16)) . '.mp4', 'video/mp4', 3000))->setTitle($title);
        @mkdir(dirname($storage->path($video)), 0775, true);
        file_put_contents($storage->path($video), $this->fakeMp4(3000));
        $this->post->addVideo($video);
        $this->em->persist($video);
        $this->em->flush();

        return $video;
    }

    /** Starts like an mp4, so it's recognised as video/mp4 */
    private function fakeMp4(int $size): string
    {
        return str_pad("\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom", $size, "\x00");
    }

    private function user(bool $admin = false): User
    {
        $user = (new User())->setEmail(uniqid('video-') . '@example.com')->setFullName('Viewer')->setPassword('not-used');
        if ($admin) {
            $role = $this->em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_ADMIN']) ?? (new Role())->setName('ROLE_ADMIN')->setDescription('ROLE_ADMIN');
            $this->em->persist($role);
            $user->addRole($role);
        }
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
