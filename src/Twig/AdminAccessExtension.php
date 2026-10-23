<?php

declare(strict_types=1);

namespace App\Twig;

use App\Security\AdminRouteAccess;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdminAccessExtension extends AbstractExtension
{
    public function __construct(private AdminRouteAccess $adminRouteAccess)
    {
    }

    public function getFunctions(): array
    {
        return [
            // {% if can_access('admin_blog_index') %}: hides admin links the user can't open
            new TwigFunction('can_access', $this->adminRouteAccess->canAccess(...)),
        ];
    }
}
