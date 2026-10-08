<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use App\Repository\BlogYouTubeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BlogYouTubeRepository::class)]
#[ORM\Table(name: 'blog_youtube')]
#[ORM\HasLifecycleCallbacks]
class BlogYouTube
{
    use TrackableTrait;

    public function __construct()
    {
        $this->dateCreated = new \DateTimeImmutable();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private string $videoId;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $playlistId = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $autoPlay = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $showControls = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $showInfo = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $modestBranding = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getVideoId(): string
    {
        return $this->videoId;
    }

    public function setVideoId(string $videoId): self
    {
        $this->videoId = $videoId;
        return $this;
    }

    public function getPlaylistId(): ?string
    {
        return $this->playlistId;
    }

    public function setPlaylistId(?string $playlistId): self
    {
        $this->playlistId = $playlistId;
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

    public function isAutoPlay(): bool
    {
        return $this->autoPlay;
    }

    public function setAutoPlay(bool $autoPlay): self
    {
        $this->autoPlay = $autoPlay;
        return $this;
    }

    public function isShowControls(): bool
    {
        return $this->showControls;
    }

    public function setShowControls(bool $showControls): self
    {
        $this->showControls = $showControls;
        return $this;
    }

    public function isShowInfo(): bool
    {
        return $this->showInfo;
    }

    public function setShowInfo(bool $showInfo): self
    {
        $this->showInfo = $showInfo;
        return $this;
    }

    public function isModestBranding(): bool
    {
        return $this->modestBranding;
    }

    public function setModestBranding(bool $modestBranding): self
    {
        $this->modestBranding = $modestBranding;
        return $this;
    }

    public function getEmbedUrl(): string
    {
        $params = [];
        if ($this->autoPlay) $params[] = 'autoplay=1';
        if (!$this->showControls) $params[] = 'controls=0';
        if (!$this->showInfo) $params[] = 'showinfo=0';
        if ($this->modestBranding) $params[] = 'modestbranding=1';
        if ($this->playlistId) $params[] = 'list=' . $this->playlistId;

        $query = !empty($params) ? '?' . implode('&', $params) : '';

        return 'https://www.youtube.com/embed/' . $this->videoId . $query;
    }

    public function getWatchUrl(): string
    {
        return 'https://www.youtube.com/watch?v=' . $this->videoId;
    }

    public function getThumbnailUrl(string $quality = 'maxresdefault'): string
    {
        return "https://img.youtube.com/vi/{$this->videoId}/{$quality}.jpg";
    }

    public function __toString(): string
    {
        return $this->title ?? $this->videoId;
    }
}