<?php

declare(strict_types=1);

namespace App\Tests\Media;

use App\Entity\BlogFile;
use App\Entity\BlogImage;
use App\Entity\BlogPost;
use App\Entity\Event;
use App\Entity\Role;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

/**
 * Media manager: browsing, searching and deleting uploads, and the file/record checks.
 * Runs against var/test-uploads (app.uploads_dir in the test environment) and a rolled back transaction.
 */
class MediaControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Connection $connection;
    private string $uploads;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->em->getConnection();
        $this->connection->beginTransaction();

        $this->uploads = (string) static::getContainer()->getParameter('app.uploads_dir');
        (new Filesystem())->remove($this->uploads);
        $this->putFile('blog/images/used.jpg');
        $this->putFile('blog/images/loose.jpg');
        $this->putFile('blog/files/report.pdf');
        $this->putFile('events/events/poster.png');
        $this->putFile('old/a.txt');
        $this->putFile('old/deeper/b.txt');

        $this->loginAsAdmin();
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        (new Filesystem())->remove($this->uploads);
        parent::tearDown();
    }

    public function testBrowseShowsFoldersFirstAndWhatUsesAFile(): void
    {
        $this->createPost('Zomerkamp', images: ['used.jpg']);

        $crawler = $this->client->request('GET', '/admin/media/');
        $this->assertResponseIsSuccessful();
        $this->assertSame(['blog', 'events', 'old'], $crawler->filter('tbody tr td:nth-child(2) strong')->each(fn ($node) => $node->text()));

        $crawler = $this->client->request('GET', '/admin/media/?folder=blog/images');
        $rows = $crawler->filter('tbody tr');
        $this->assertSame(2, $rows->count());
        $this->assertStringContainsString('Niet in gebruik', $rows->eq(0)->text(), 'loose.jpg');
        $this->assertStringContainsString('Blog: Zomerkamp', $rows->eq(1)->text(), 'used.jpg');
        $this->assertCount(1, $rows->eq(1)->filter('button[disabled]'), 'a used file can\'t be deleted');
        $this->assertSame('/uploads/blog/images/loose.jpg', $rows->eq(0)->filter('img')->attr('src'));
        $this->assertSame(['uploads', 'blog', 'images'], $crawler->filter('.breadcrumb-item')->each(fn ($node) => trim($node->text())));
    }

    public function testSearchLooksInSubFolders(): void
    {
        $crawler = $this->client->request('GET', '/admin/media/?q=B.TXT');

        $this->assertResponseIsSuccessful();
        $this->assertSame(['b.txt'], $crawler->filter('tbody tr td:nth-child(2) a')->each(fn ($node) => $node->text()));
        $this->assertStringContainsString('old/deeper', $crawler->filter('tbody')->text());
    }

    public function testPathsOutsideTheUploadsFolderAreRefused(): void
    {
        $this->client->request('GET', '/admin/media/?folder=../../config');
        $this->assertResponseRedirects('/admin/media/');

        $this->delete('../.env');
        $this->assertResponseStatusCodeSame(422);
        $this->assertFileExists(static::getContainer()->getParameter('kernel.project_dir') . '/.env');
    }

    public function testDeleteFileAndFolderButNotWhatIsInUse(): void
    {
        $this->createPost('Zomerkamp', images: ['used.jpg']);

        $this->delete('blog/images/used.jpg');
        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('nog in gebruik (Blog: Zomerkamp)', (string) $this->client->getResponse()->getContent());
        $this->assertFileExists($this->uploads . '/blog/images/used.jpg');

        $this->delete('blog');
        $this->assertResponseStatusCodeSame(422, 'a folder with a used file stays');

        $this->delete('blog/images/loose.jpg');
        $this->assertResponseIsSuccessful();
        $this->assertFileDoesNotExist($this->uploads . '/blog/images/loose.jpg');

        $this->delete('old');
        $this->assertResponseIsSuccessful();
        $this->assertDirectoryDoesNotExist($this->uploads . '/old');
    }

    public function testDeleteNeedsTheTokenOfThatPath(): void
    {
        $this->client->request('GET', '/admin/media/?folder=blog/images');
        $tokenOfOtherFile = $this->client->getCrawler()->filter('[data-csrf-token]')->first()->attr('data-csrf-token');

        $this->client->request('POST', '/admin/media/delete?path=old/a.txt', [], [], ['HTTP_X_CSRF_TOKEN' => (string) $tokenOfOtherFile]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertFileExists($this->uploads . '/old/a.txt');
    }

    public function testCheckListsUnusedFilesAndDeletesTheSelectedOnes(): void
    {
        $this->createPost('Zomerkamp', images: ['used.jpg'], files: ['report.pdf']);
        $this->createEvent('Regatta', 'poster.png');

        $crawler = $this->client->request('GET', '/admin/media/check');
        $paths = $crawler->filter('#unused input[name="paths[]"]')->each(fn ($node) => $node->attr('value'));
        $this->assertSame(['blog/images/loose.jpg', 'old/a.txt', 'old/deeper/b.txt'], $paths);

        $form = $crawler->filter('#unused form')->form();
        $form['paths'][0]->tick();
        // A file that became used in the meantime is not deleted
        $this->client->request('POST', '/admin/media/check/unused-files', [
            '_token' => $form['_token']->getValue(),
            'paths' => ['blog/images/loose.jpg', 'blog/images/used.jpg', '../../.env'],
        ]);

        $this->assertResponseRedirects('/admin/media/check');
        $this->assertFileDoesNotExist($this->uploads . '/blog/images/loose.jpg');
        $this->assertFileExists($this->uploads . '/blog/images/used.jpg');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-warning, .alert-success', '1 van 3 geselecteerde bestand(en) verwijderd.');
    }

    public function testCheckListsRecordsWithoutFileAndRemovesThemFromPostsAndEvents(): void
    {
        $post = $this->createPost('Zomerkamp', images: ['used.jpg', 'gone.jpg'], files: ['gone.pdf']);
        $event = $this->createEvent('Regatta', 'gone-poster.png');
        $external = $this->image('https://placehold.co/800x400/png', 'x');
        $this->em->persist($external);
        $this->em->flush();

        $ids = fn (string $filename, string $table) => (string) $this->connection->fetchOne('SELECT MAX(id) FROM ' . $table . ' WHERE filename = ?', [$filename]);
        $ours = ['image:' . $ids('gone.jpg', 'blog_image'), 'image:' . $ids('gone-poster.png', 'blog_image'), 'file:' . $ids('gone.pdf', 'blog_file')];

        // (The test database can hold records of other tests too, so only ours are checked)
        $crawler = $this->client->request('GET', '/admin/media/check');
        $records = $crawler->filter('#missing input[name="records[]"]')->each(fn ($node) => $node->attr('value'));
        $this->assertSame([], array_diff($ours, $records), 'gone.jpg, gone-poster.png and gone.pdf are listed');
        $this->assertNotContains('image:' . $external->getId(), $records, 'an external URL is skipped');
        $this->assertNotContains('image:' . $ids('used.jpg', 'blog_image'), $records);
        $this->assertStringContainsString('Evenement: Regatta', $crawler->filter('#missing')->text());

        $this->client->request('POST', '/admin/media/check/missing-files', [
            '_token' => $crawler->filter('#missing input[name="_token"]')->attr('value'),
            'records' => $ours,
        ]);
        $this->assertResponseRedirects('/admin/media/check#missing');

        $this->assertSame(['used.jpg'], $this->connection->fetchFirstColumn(
            'SELECT i.filename FROM blog_image i JOIN blog_post_image pi ON pi.blog_image_id = i.id WHERE pi.blog_post_id = ?', [$post->getId()]
        ));
        $this->assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM blog_post_file WHERE blog_post_id = ?', [$post->getId()]));
        $this->assertNull($this->connection->fetchOne('SELECT image_id FROM events WHERE id = ?', [$event->getId()]));
        $this->assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM blog_image WHERE id = ?', [$external->getId()]));
    }

    private function putFile(string $path): void
    {
        (new Filesystem())->dumpFile($this->uploads . '/' . $path, 'test ' . $path);
    }

    private function delete(string $path): void
    {
        // The page only renders tokens for deletable entries, so make one in the browser's session like the page would
        $this->client->request('GET', '/admin/media/');
        $session = $this->client->getRequest()->getSession();
        $request = new Request();
        $request->setSession($session);
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push($request);
        $token = static::getContainer()->get('security.csrf.token_manager')->getToken('media_delete' . $path)->getValue();
        $requestStack->pop();
        $session->save();
        $this->client->request('POST', '/admin/media/delete?path=' . rawurlencode($path), [], [], ['HTTP_X_CSRF_TOKEN' => $token]);
    }

    /**
     * @param list<string> $images files in blog/images
     * @param list<string> $files files in blog/files
     */
    private function createPost(string $title, array $images = [], array $files = []): BlogPost
    {
        $post = (new BlogPost())->setTitle($title)->setSlug('media-test-' . uniqid())->setIntroText('Intro')->setContent('Content');
        foreach ($images as $filename) {
            $post->addImage($this->image($this->uploads . '/blog/images', $filename));
        }
        foreach ($files as $filename) {
            $file = (new BlogFile())->setFilename($filename)->setPath($this->uploads . '/blog/files');
            $post->addFile($file);
        }
        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }

    private function createEvent(string $title, string $poster): Event
    {
        $event = (new Event())->setTitle($title)
            ->setEventStartDate(new \DateTimeImmutable('2030-06-01'))
            ->setEventEndDate(new \DateTimeImmutable('2030-06-01'))
            // Stored with another server's absolute path: only the part after "uploads/" counts
            ->setEventImage($this->image('/srv/elsewhere/public/uploads/events/events', $poster));
        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }

    private function image(string $path, string $filename): BlogImage
    {
        return (new BlogImage())->setFilename($filename)->setPath($path);
    }

    private function loginAsAdmin(): void
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_ADMIN']) ?? (new Role())->setName('ROLE_ADMIN')->setDescription('ROLE_ADMIN');
        $this->em->persist($role);
        $user = new User();
        $user->setEmail(uniqid('media-') . '@example.com');
        $user->setFullName('Media test');
        $user->setPassword('not-used');
        $user->addRole($role);
        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user, 'primary_auth');
    }
}
