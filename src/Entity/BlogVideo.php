<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BlogVideoRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A video file with a blog post, only for logged in visitors. The file lives outside public/ (see BlogVideoStorage)
 * and is only served by BlogVideoController, which checks the login.
 */
#[ORM\Entity(repositoryClass: BlogVideoRepository::class)]
#[ORM\Table(name: 'blog_video')]
class BlogVideo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BlogPost::class, inversedBy: 'videos')]
    #[ORM\JoinColumn(name: 'blog_post_id', nullable: false, onDelete: 'CASCADE')]
    private BlogPost $blogPost;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    /** Random name in the storage folder, so it can't be guessed */
    #[ORM\Column(length: 255)]
    private string $filename;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $originalName = null;

    #[ORM\Column(length: 100)]
    private string $mimeType;

    #[ORM\Column(type: 'bigint')]
    private int $fileSize = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $dateCreated;

    public function __construct(BlogPost $blogPost, string $filename, string $mimeType, int $fileSize)
    {
        $this->blogPost = $blogPost;
        $this->filename = $filename;
        $this->mimeType = $mimeType;
        $this->fileSize = $fileSize;
        $this->dateCreated = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBlogPost(): BlogPost
    {
        return $this->blogPost;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title !== null && trim($title) !== '' ? trim($title) : null;

        return $this;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(?string $originalName): self
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getFileSize(): int
    {
        return (int) $this->fileSize;
    }

    public function getHumanReadableSize(): string
    {
        $size = (float) $this->getFileSize();
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($size < 1024 || $unit === 'GB') {
                return round($size, $unit === 'B' ? 0 : 1) . ' ' . $unit;
            }
            $size /= 1024;
        }

        return '';
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getDateCreated(): \DateTimeImmutable
    {
        return $this->dateCreated;
    }
}
