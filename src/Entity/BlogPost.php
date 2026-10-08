<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use App\Repository\BlogPostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: BlogPostRepository::class)]
#[ORM\Table(name: 'blog_post')]
#[UniqueEntity(fields: ['slug'], message: 'This slug is already in use')]
#[ORM\HasLifecycleCallbacks]
class BlogPost
{
    use TrackableTrait;

    public const STATUS_DRAFT = 0;
    public const STATUS_ONLINE = 1;
    public const STATUS_OFFLINE = 2;
    public const STATUS_ARCHIVED = 3;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $slug;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $introText;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'smallint', options: ['default' => self::STATUS_DRAFT])]
    private int $status = self::STATUS_DRAFT;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateOnline = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateOffline = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $timeOnline = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $timeOffline = null;

    /** @var Collection<int, Category> */
    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'blogPosts', cascade: ['persist'])]
    #[ORM\JoinTable(name: 'blog_post_category')]
    #[ORM\JoinColumn(name: 'blog_post_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'blog_category_id', referencedColumnName: 'id')]
    private Collection $categories;

    /** @var Collection<int, BlogImage> */
    #[ORM\ManyToMany(targetEntity: BlogImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinTable(name: 'blog_post_image')]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $images;

    /** @var Collection<int, BlogFile> */
    #[ORM\ManyToMany(targetEntity: BlogFile::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinTable(name: 'blog_post_file')]
    private Collection $files;

    /** @var Collection<int, BlogYouTube> */
    #[ORM\ManyToMany(targetEntity: BlogYouTube::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinTable(name: 'blog_post_youtube')]
    private Collection $youTubes;

    /** @var Collection<int, BlogComment> */
    #[ORM\OneToMany(mappedBy: 'blogPost', targetEntity: BlogComment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['dateCreated' => 'ASC'])]
    private Collection $comments;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->files = new ArrayCollection();
        $this->youTubes = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getIntroText(): string
    {
        return $this->introText;
    }

    public function setIntroText(string $introText): self
    {
        $this->introText = $introText;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function isOnline(): bool
    {
        return $this->status === self::STATUS_ONLINE;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function getDateOnline(): ?\DateTimeImmutable
    {
        return $this->dateOnline;
    }

    public function setDateOnline(?\DateTimeImmutable $dateOnline): self
    {
        $this->dateOnline = $dateOnline;
        return $this;
    }

    public function getDateOffline(): ?\DateTimeImmutable
    {
        return $this->dateOffline;
    }

    public function setDateOffline(?\DateTimeImmutable $dateOffline): self
    {
        $this->dateOffline = $dateOffline;
        return $this;
    }

    public function getTimeOnline(): ?\DateTimeImmutable
    {
        return $this->timeOnline;
    }

    public function setTimeOnline(?\DateTimeImmutable $timeOnline): self
    {
        $this->timeOnline = $timeOnline;
        return $this;
    }

    public function getTimeOffline(): ?\DateTimeImmutable
    {
        return $this->timeOffline;
    }

    public function setTimeOffline(?\DateTimeImmutable $timeOffline): self
    {
        $this->timeOffline = $timeOffline;
        return $this;
    }

    /** @return Collection<int, Category> */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): self
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
            $category->addBlogPost($this);
        }
        return $this;
    }

    public function removeCategory(Category $category): self
    {
        if ($this->categories->contains($category)) {
            $this->categories->removeElement($category);
            $category->removeBlogPost($this);
        }
        return $this;
    }

    /** @return Collection<int, BlogImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(BlogImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
        }
        return $this;
    }

    public function removeImage(BlogImage $image): self
    {
        if ($this->images->contains($image)) {
            $this->images->removeElement($image);
        }
        return $this;
    }

    /** @return Collection<int, BlogFile> */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(BlogFile $file): self
    {
        if (!$this->files->contains($file)) {
            $this->files->add($file);
        }
        return $this;
    }

    public function removeFile(BlogFile $file): self
    {
        if ($this->files->contains($file)) {
            $this->files->removeElement($file);
        }
        return $this;
    }

    /** @return Collection<int, BlogYouTube> */
    public function getYouTubes(): Collection
    {
        return $this->youTubes;
    }

    public function addYouTube(BlogYouTube $youTube): self
    {
        if (!$this->youTubes->contains($youTube)) {
            $this->youTubes->add($youTube);
        }
        return $this;
    }

    public function removeYouTube(BlogYouTube $youTube): self
    {
        if ($this->youTubes->contains($youTube)) {
            $this->youTubes->removeElement($youTube);
        }
        return $this;
    }

    /** @return Collection<int, BlogComment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(BlogComment $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setBlogPost($this);
        }
        return $this;
    }

    public function removeComment(BlogComment $comment): self
    {
        if ($this->comments->contains($comment)) {
            $this->comments->removeElement($comment);
        }
        return $this;
    }

    public function getRootComments(): array
    {
        return $this->comments
            ->filter(fn(BlogComment $c) => $c->getParent() === null)
            ->toArray();
    }

    public function getCommentCount(): int
    {
        return $this->comments->count();
    }

    public function isScheduledForPublishing(): bool
    {
        if ($this->status !== self::STATUS_ONLINE) {
            return false;
        }
        if ($this->dateOnline === null) {
            return false;
        }
        return $this->dateOnline > new \DateTimeImmutable();
    }

    public function isScheduledForUnpublishing(): bool
    {
        if ($this->status !== self::STATUS_ONLINE) {
            return false;
        }
        if ($this->dateOffline === null) {
            return false;
        }
        return $this->dateOffline < new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->title;
    }
}