<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\MenuItemRepository::class)]
#[ORM\Table(name: 'menu_items')]
#[ORM\HasLifecycleCallbacks]
class MenuItem
{
    use TrackableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $link = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $icon = 'fas fa-home';

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 9999;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $authorizedFor = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $openInNewTab = false;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $cssClass = null;

    /** @var Collection<int, MenuItem> */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: MenuItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $children;

    #[ORM\ManyToOne(targetEntity: MenuItem::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: true)]
    private ?MenuItem $parent = null;

    #[ORM\ManyToOne(targetEntity: Menu::class, inversedBy: 'menuItems')]
    #[ORM\JoinColumn(name: 'menu_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: true)]
    private ?Menu $menu = null;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->dateCreated = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): self
    {
        $this->link = $link;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;
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

    public function getAuthorizedFor(): ?string
    {
        return $this->authorizedFor;
    }

    public function setAuthorizedFor(?string $authorizedFor): self
    {
        $this->authorizedFor = $authorizedFor;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function isOpenInNewTab(): bool
    {
        return $this->openInNewTab;
    }

    public function setOpenInNewTab(bool $openInNewTab): self
    {
        $this->openInNewTab = $openInNewTab;
        return $this;
    }

    public function getCssClass(): ?string
    {
        return $this->cssClass;
    }

    public function setCssClass(?string $cssClass): self
    {
        $this->cssClass = $cssClass;
        return $this;
    }

    public function getParent(): ?MenuItem
    {
        return $this->parent;
    }

    public function setParent(?MenuItem $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    /** @return Collection<int, MenuItem> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(MenuItem $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
            $child->setMenu($this->menu);
        }
        return $this;
    }

    public function removeChild(MenuItem $child): self
    {
        if ($this->children->contains($child)) {
            $this->children->removeElement($child);
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }
        return $this;
    }

    public function getMenu(): ?Menu
    {
        return $this->menu;
    }

    public function setMenu(?Menu $menu): self
    {
        $this->menu = $menu;
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

    /** @return array<int, MenuItem> */
    public function getAncestors(): array
    {
        $ancestors = [];
        $current = $this->parent;
        while ($current !== null) {
            $ancestors[] = $current;
            $current = $current->getParent();
        }
        return array_reverse($ancestors);
    }

    /** @return array<int, MenuItem> */
    public function getPath(): array
    {
        $path = $this->getAncestors();
        $path[] = $this;
        return $path;
    }

    public function getChildCount(): int
    {
        return $this->children->count();
    }

    public function __toString(): string
    {
        return $this->label;
    }
}