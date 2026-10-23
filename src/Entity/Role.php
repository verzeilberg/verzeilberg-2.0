<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \App\Repository\RoleRepository::class)]
#[ORM\Table(name: 'role')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity('name')]
class Role
{
    use TrackableTrait;

    /** Roles the application relies on (security.yaml, access checks); they can't be renamed or deleted */
    public const SYSTEM_ROLES = ['ROLE_ADMIN', 'ROLE_USER'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^ROLE_[A-Z0-9_]+$/', message: 'Een rolnaam begint met ROLE_ en bevat alleen hoofdletters, cijfers en _, bijv. ROLE_EDITOR')]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * The roles this role inherits permissions from
     *
     * @var Collection<int, Role>
     */
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
        joinColumns: [new ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id', onDelete: 'CASCADE')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'permission_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
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

    public function setName(?string $name): self
    {
        $this->name = strtoupper(trim((string) $name));
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

    /** Form adder for "parentRoles" */
    public function addParentRole(Role $role): self
    {
        return $this->addParent($role);
    }

    public function removeParentRole(Role $role): self
    {
        return $this->removeParent($role);
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

    public function isSystemRole(): bool
    {
        return in_array($this->name, self::SYSTEM_ROLES, true);
    }

    public function hasPermission(string $permissionName): bool
    {
        foreach ($this->getAllPermissions() as $permission) {
            if ($permission->getName() === $permissionName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Own permissions plus those inherited from parent roles (and their parents)
     *
     * @return array<string, Permission> keyed by name, sorted
     */
    public function getAllPermissions(): array
    {
        $permissions = [];
        foreach ($this->getAncestorsAndSelf() as $role) {
            foreach ($role->getPermissions() as $permission) {
                $permissions[$permission->getName()] = $permission;
            }
        }
        ksort($permissions);

        return $permissions;
    }

    /** @return list<Role> this role and every role it inherits from; safe for cycles */
    public function getAncestorsAndSelf(): array
    {
        $seen = [];
        $queue = [$this];
        while ($role = array_shift($queue)) {
            if (in_array($role, $seen, true)) {
                continue;
            }
            $seen[] = $role;
            foreach ($role->getParentRoles() as $parent) {
                $queue[] = $parent;
            }
        }

        return $seen;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}