<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use App\Repository\RoutePermissionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Grants a permission access to one or more admin routes. Admin routes without a matching
 * rule are for ROLE_ADMIN only (see App\Security\AdminRouteAccess).
 */
#[ORM\Entity(repositoryClass: RoutePermissionRepository::class)]
#[ORM\Table(name: 'route_permission')]
#[ORM\HasLifecycleCallbacks]
class RoutePermission
{
    use TrackableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /** Route name, "*" matches anything: "admin_blog_*" covers every blog admin page */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9_.*]+$/', message: 'Use a route name such as admin_blog_index; * matches anything.')]
    private string $routePattern = '';

    #[ORM\ManyToOne(targetEntity: Permission::class, inversedBy: 'routePermissions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Kies het recht dat toegang geeft.')]
    private Permission $permission;

    /** @var list<string>|null null = every HTTP method */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $methods = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    public function __construct()
    {
        $this->dateCreated = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoutePattern(): string
    {
        return $this->routePattern;
    }

    public function setRoutePattern(?string $routePattern): self
    {
        $this->routePattern = strtolower(trim((string) $routePattern));
        return $this;
    }

    /** Null only for a new rule whose permission isn't chosen yet */
    public function getPermission(): ?Permission
    {
        return $this->permission ?? null;
    }

    /** Null (the form's empty choice) is ignored; NotNull then reports it for a new rule */
    public function setPermission(?Permission $permission): self
    {
        if ($permission !== null) {
            $this->permission = $permission;
        }
        return $this;
    }

    public function getMethods(): ?array
    {
        return $this->methods;
    }

    public function setMethods(?array $methods): self
    {
        $this->methods = $methods ? array_values($methods) : null;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function matchesRouteName(string $route): bool
    {
        $regex = '#^' . str_replace('\*', '.*', preg_quote($this->routePattern, '#')) . '$#';

        return (bool) preg_match($regex, $route);
    }

    public function matches(string $route, ?string $method = null): bool
    {
        if (!$this->enabled || !$this->matchesRouteName($route)) {
            return false;
        }

        return $this->methods === null || $method === null || in_array(strtoupper($method), $this->methods, true);
    }

    /** Exact names beat wildcards, longer patterns beat shorter ones: admin_blog_delete before admin_blog_* before admin_* */
    public function getSpecificity(): int
    {
        return (str_contains($this->routePattern, '*') ? 0 : 1000) + strlen($this->routePattern);
    }

    public function __toString(): string
    {
        return $this->routePattern . ' → ' . ($this->getPermission()?->getName() ?? '?');
    }
}
