<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TrackableTrait;
use App\Repository\RoutePermissionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

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

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $routePattern;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $permissionName;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $methods = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $priority = 0;

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

    public function setRoutePattern(string $routePattern): self
    {
        $this->routePattern = $routePattern;
        return $this;
    }

    public function getPermissionName(): string
    {
        return $this->permissionName;
    }

    public function setPermissionName(string $permissionName): self
    {
        $this->permissionName = $permissionName;
        return $this;
    }

    public function getMethods(): ?array
    {
        return $this->methods;
    }

    public function setMethods(?array $methods): self
    {
        $this->methods = $methods;
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

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
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

    public function matches(string $route, ?string $method = null): bool
    {
        if (!$this->enabled) {
            return false;
        }

        // Convert route pattern to regex
        $pattern = preg_replace('/\{[^}]+\}/', '[^/]+', $this->routePattern);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $route)) {
            return false;
        }

        if ($this->methods !== null && $method !== null && !in_array(strtoupper($method), $this->methods)) {
            return false;
        }

        return true;
    }

    public function __toString(): string
    {
        return $this->routePattern . ' → ' . $this->permissionName;
    }
}