<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\RoleRepository::class)]
#[ORM\Table(name: 'role')]
#[ORM\HasLifecycleCallbacks]
class Role
{
    use TrackableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** @var Collection<int, Role> */
    #[ORM\ManyToMany(targetEntity: Role::class, mappedBy: 'childRoles')]
    private Collection $parentRoles;

    /** @var Collection<int, Role> */
    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'parentRoles')]
    #[ORM\JoinTable(
        name: 'role_hierarchy',
        joinColumns: [new ORM\JoinColumn(name: 'parent_role_id', referencedColumnName: 'id', onDelete: 'CASCADE')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'child_role_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    )]
    private Collection $childRoles;

    /** @var Collection<int, Permission> */
    #[ORM\ManyToMany(targetEntity: Permission::class, inversedBy: 'roles')]
    #[ORM\JoinTable(
        name: 'role_permission',
        joinColumns: [new ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'permission_id', referencedColumnName: 'id')]
    )]
    private Collection $permissions;

    public function __construct()
    {
        $this->parentRoles = new ArrayCollection();
        $this->childRoles = new ArrayCollection();
        $this->permissions = new ArrayCollection();
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
    public function getParentRoles(): Collection
    {
        return $this->parentRoles;
    }

    /** @return Collection<int, Role> */
    public function getChildRoles(): Collection
    {
        return $this->childRoles;
    }

    /** @return Collection<int, Permission> */
    public function getPermissions(): Collection
    {
        return $this->permissions;
    }

    public function addParent(Role $role): self
    {
        if ($this->id !== null && $this->id === $role->getId()) {
            return $this;
        }
        if (!$this->parentRoles->contains($role)) {
            $this->parentRoles->add($role);
            $role->addChild($this);
        }
        return $this;
    }

    public function addChild(Role $role): self
    {
        if (!$this->childRoles->contains($role)) {
            $this->childRoles->add($role);
            $role->addParent($this);
        }
        return $this;
    }

    public function removeParent(Role $role): self
    {
        if ($this->parentRoles->contains($role)) {
            $this->parentRoles->removeElement($role);
            $role->removeChild($this);
        }
        return $this;
    }

    public function removeChild(Role $role): self
    {
        if ($this->childRoles->contains($role)) {
            $this->childRoles->removeElement($role);
            $role->removeParent($this);
        }
        return $this;
    }

    public function addPermission(Permission $permission): self
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions->add($permission);
            $permission->addRole($this);
        }
        return $this;
    }

    public function removePermission(Permission $permission): self
    {
        if ($this->permissions->contains($permission)) {
            $this->permissions->removeElement($permission);
            $permission->removeRole($this);
        }
        return $this;
    }

    public function hasPermission(string $permissionName): bool
    {
        if ($this->permissions->exists(fn(int $key, Permission $p) => $p->getName() === $permissionName)) {
            return true;
        }

        foreach ($this->parentRoles as $parent) {
            if ($parent->hasPermission($permissionName)) {
                return true;
            }
        }

        return false;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}