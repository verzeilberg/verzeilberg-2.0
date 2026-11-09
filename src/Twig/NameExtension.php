<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\NameMasker;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * {{ post.content|names|raw }}: [[names]] as the full name for logged in visitors, as initials for others.
 * |names(false) always gives initials (for public, cacheable output like the RSS feed).
 * can_see_private(): whether the visitor may see what's for logged in visitors only (names, blog videos).
 */
class NameExtension extends AbstractExtension
{
    public function __construct(private NameMasker $nameMasker) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('names', [$this->nameMasker, 'forViewer']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('can_see_private', [$this->nameMasker, 'canSeeNames']),
        ];
    }
}
