<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use App\Entity\Traits\UploadedFileTrait;
use App\Repository\BlogImageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BlogImageRepository::class)]
#[ORM\Table(name: 'blog_image')]
#[ORM\HasLifecycleCallbacks]
class BlogImage
{
    use TrackableTrait;
    use UploadedFileTrait;

    public function __construct()
    {
        $this->dateCreated = new \DateTimeImmutable();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(length: 255)]
    private string $filename;

    #[ORM\Column(length: 500)]
    private string $path;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $imageType = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $width = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $height = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): self
    {
        $this->alt = $alt;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
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

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): self
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getImageType(): ?string
    {
        return $this->imageType;
    }

    public function setImageType(?string $imageType): self
    {
        $this->imageType = $imageType;
        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): self
    {
        $this->width = $width;
        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): self
    {
        $this->height = $height;
        return $this;
    }

    public function getUrl(): string
    {
        if ($this->isExternalUrl()) {
            return $this->path . $this->filename;
        }
        return $this->path . $this->filename;
    }

    public function getPublicPath(): string
    {
        if ($this->isExternalUrl()) {
            return $this->path . $this->filename;
        }
        return self::webPath($this->path, $this->filename);
    }

    private function isExternalUrl(): bool
    {
        return str_starts_with($this->path, 'http://') || str_starts_with($this->path, 'https://');
    }

    public function __toString(): string
    {
        return $this->name ?? $this->filename;
    }
}