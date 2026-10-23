<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \App\Repository\PermissionRepository::class)]
#[ORM\Table(name: 'permission')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity('name')]
class Permission
{
    use TrackableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /** Lowercase, dot-separated: "blog.edit"; the part before the first dot is its group */
    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9_]+(\.[a-z0-9_]+)*$/', message: 'Gebruik woorden in kleine letters, gescheiden door punten, bijv. blog.edit')]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** @var Collection<int, Role> */
    #[ORM\ManyToMany(targetEntity: Role::class, mappedBy: 'permissions')]
    private Collection $roles;

    /** @var Collection<int, RoutePermission> */
    #[ORM\OneToMany(targetEntity: RoutePermission::class, mappedBy: 'permission')]
    private Collection $routePermissions;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
        $this->routePermissions = new ArrayCollection();
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

    public function setName(?string $name): self
    {
        $this->name = strtolower(trim((string) $name));
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

    /** @return Collection<int, Role> */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    public function getGroup(): string
    {
        return explode('.', $this->name)[0];
    }

    /** @return Collection<int, RoutePermission> */
    public function getRoutePermissions(): Collection
    {
        return $this->routePermissions;
    }

    public function addRole(Role $role): self
    {
        if (!$this->roles->contains($role)) {
            $this->roles->add($role);
            $role->addPermission($this);
        }
        return $this;
    }

    public function removeRole(Role $role): self
    {
        if ($this->roles->contains($role)) {
            $this->roles->removeElement($role);
            $role->removePermission($this);
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}