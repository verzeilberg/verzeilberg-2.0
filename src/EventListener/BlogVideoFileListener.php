<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\BlogVideo;
use App\Service\BlogVideoStorage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Removes the video file when its BlogVideo is removed: deleted by itself, or with its blog post.
 */
#[AsEntityListener(event: Events::postRemove, entity: BlogVideo::class)]
class BlogVideoFileListener
{
    public function __construct(private BlogVideoStorage $storage) {}

    public function postRemove(BlogVideo $video): void
    {
        $this->storage->delete($video);
    }
}
