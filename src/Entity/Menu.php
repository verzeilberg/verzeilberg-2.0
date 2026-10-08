<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\MenuRepository::class)]
#[ORM\Table(name: 'menu')]
#[ORM\HasLifecycleCallbacks]
class Menu
{
    use TrackableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    /** @var Collection<int, MenuItem> */
    #[ORM\OneToMany(mappedBy: 'menu', targetEntity: MenuItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $menuItems;

    public function __construct()
    {
        $this->menuItems = new ArrayCollection();
        $this->dateCreated = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /** @return Collection<int, MenuItem> */
    public function getMenuItems(): Collection
    {
        return $this->menuItems;
    }

    /** @return Collection<int, MenuItem> */
    public function getRootMenuItems(): Collection
    {
        return $this->menuItems->filter(fn(MenuItem $item) => $item->getParent() === null);
    }

    public function addMenuItem(MenuItem $menuItem): self
    {
        if (!$this->menuItems->contains($menuItem)) {
            $this->menuItems->add($menuItem);
            $menuItem->setMenu($this);
        }
        return $this;
    }

    public function removeMenuItem(MenuItem $menuItem): self
    {
        if ($this->menuItems->contains($menuItem)) {
            $this->menuItems->removeElement($menuItem);
            if ($menuItem->getMenu() === $this) {
                $menuItem->setMenu(null);
            }
        }
        return $this;
    }

    public function getMenuItemCount(): int
    {
        return $this->menuItems->count();
    }

    public function __toString(): string
    {
        return $this->name;
    }
}