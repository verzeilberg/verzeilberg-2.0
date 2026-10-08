<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use App\Repository\BlogCommentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BlogCommentRepository::class)]
#[ORM\Table(name: 'blog_comment')]
#[ORM\HasLifecycleCallbacks]
class BlogComment
{
    use TrackableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\ManyToOne(targetEntity: BlogPost::class, inversedBy: 'comments')]
    #[ORM\JoinColumn(name: 'blog_post_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    private BlogPost $blogPost;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    private ?User $author = null;

    #[ORM\ManyToOne(targetEntity: BlogComment::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: true)]
    private ?BlogComment $parent = null;

    /** @var Collection<int, BlogComment> */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: BlogComment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['dateCreated' => 'ASC'])]
    private Collection $children;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isApproved = false;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->dateCreated = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getBlogPost(): BlogPost
    {
        return $this->blogPost;
    }

    public function setBlogPost(BlogPost $blogPost): self
    {
        $this->blogPost = $blogPost;
        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;
        return $this;
    }

    public function getParent(): ?BlogComment
    {
        return $this->parent;
    }

    public function setParent(?BlogComment $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    /** @return Collection<int, BlogComment> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(BlogComment $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }
        return $this;
    }

    public function removeChild(BlogComment $child): self
    {
        if ($this->children->contains($child)) {
            $this->children->removeElement($child);
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }
        return $this;
    }

    public function isApproved(): bool
    {
        return $this->isApproved;
    }

    public function setApproved(bool $approved): self
    {
        $this->isApproved = $approved;
        return $this;
    }

    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    public function hasChildren(): bool
    {
        return !$this->children->isEmpty();
    }

    public function getDepth(): int
    {
        $depth = 0;
        $current = $this->parent;
        while ($current !== null) {
            $depth++;
            $current = $current->getParent();
        }
        return $depth;
    }

    public function __toString(): string
    {
        return mb_strimwidth($this->content, 0, 50, '...');
    }
}