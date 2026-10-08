<?php

namespace App\Tests\Functional;

use App\Entity\BlogPost;
use App\Entity\Role;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The blog form uses the Trix editor for the content: the editor must be connected to the content field,
 * otherwise what is typed is not submitted.
 */
class BlogFormTest extends WebTestCase
{
    public function testContentFieldUsesTheTrixEditor(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        $connection->beginTransaction();

        try {
            $role = $em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_ADMIN']) ?? (new Role())->setName('ROLE_ADMIN')->setDescription('Administrator');
            $em->persist($role);
            $admin = new User();
            $admin->setEmail(uniqid('blog-form-') . '@example.com');
            $admin->setFullName('Blog form test');
            $admin->setPassword('not-used');
            $admin->addRole($role);
            $em->persist($admin);

            $post = new BlogPost();
            $post->setTitle('Trix test');
            $post->setSlug('trix-test-' . uniqid());
            $post->setIntroText('Intro');
            $post->setContent('<p>Content</p>');
            $em->persist($post);
            $em->flush();

            $client->loginUser($admin, 'primary_auth');

            foreach (['/admin/blog/new', '/admin/blog/' . $post->getId() . '/edit'] as $url) {
                $crawler = $client->request('GET', $url);
                $this->assertResponseIsSuccessful($url);

                $content = $crawler->filter('[name="blog_post[content]"]');
                $editor = $crawler->filter('trix-editor');
                $this->assertCount(1, $content, $url . ': content field');
                $this->assertCount(1, $editor, $url . ': Trix editor');
                $this->assertSame($content->attr('id'), $editor->attr('input'), $url . ': the editor writes to the content field');
                $this->assertStringContainsString('trix.umd.min.js', $client->getResponse()->getContent(), $url . ': Trix script');
            }
        } finally {
            $connection->rollBack();
        }
    }
}
