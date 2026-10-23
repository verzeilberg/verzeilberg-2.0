<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\BlogFile;
use App\Entity\BlogImage;
use PHPUnit\Framework\TestCase;

/**
 * The URL of an uploaded image/file doesn't depend on the server folder the project is in.
 */
class UploadedFilePathTest extends TestCase
{
    public function testWebPathIsWhatFollowsPublic(): void
    {
        foreach (['/var/www/verzeilberg-2.0-dev/public/uploads/blog/images', '/srv/www/site/releases/42/public/uploads/blog/images/', 'C:\\www\\site\\public\\uploads\\blog\\images'] as $folder) {
            $image = (new BlogImage())->setPath($folder)->setFilename('photo.jpg');
            $this->assertSame('/uploads/blog/images/photo.jpg', $image->getPublicPath(), $folder);
        }

        $file = (new BlogFile())->setPath('/home/deploy/public_html/public/uploads/blog/files')->setFilename('report.pdf');
        $this->assertSame('/uploads/blog/files/report.pdf', $file->getPublicPath(), '"public_html" is not "public"');
    }

    public function testWebPathAndExternalUrlStayAsTheyAre(): void
    {
        $this->assertSame('/uploads/events/events/poster.png', (new BlogImage())->setPath('/uploads/events/events')->setFilename('poster.png')->getPublicPath());
        $this->assertSame('https://placehold.co/800x400/png', (new BlogImage())->setPath('https://placehold.co/800x400/')->setFilename('png')->getPublicPath());
    }

    public function testRelativeFolderIsUnderPublic(): void
    {
        $image = (new BlogImage())->setPath('uploads/blog/images')->setFilename('photo.jpg');
        $this->assertSame('/uploads/blog/images/photo.jpg', $image->getPublicPath());
        $this->assertSame('/home/site/public/uploads/blog/images', $image->getFolder('/home/site/public/'));
        $this->assertSame('/home/site/public/uploads/blog/images/photo.jpg', $image->getFilePath('/home/site/public'));
    }

    public function testAbsoluteFolderStaysOnDisk(): void
    {
        $file = (new BlogFile())->setPath('/var/www/old/public/uploads/blog/files/')->setFilename('report.pdf');
        $this->assertSame('/var/www/old/public/uploads/blog/files/report.pdf', $file->getFilePath('/home/site/public'));
    }
}
