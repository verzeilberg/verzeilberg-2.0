<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Menu;
use App\Entity\MenuItem;
use App\Repository\MenuItemRepository;
use App\Repository\MenuRepository;
use App\Security\Authorization\MenuAuthorizationChecker;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MenuExtension extends AbstractExtension
{
    public function __construct(
        private MenuRepository $menuRepository,
        private MenuItemRepository $menuItemRepository,
        private MenuAuthorizationChecker $authorizationChecker,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_menu', [$this, 'getMenu']),
            new TwigFunction('render_menu', [$this, 'renderMenu'], ['is_safe' => ['html']]),
            new TwigFunction('render_menu_item', [$this, 'renderMenuItem'], ['is_safe' => ['html']]),
        ];
    }

    public function getMenu(string $name): ?Menu
    {
        return $this->menuRepository->findByName($name);
    }

    public function renderMenu(Menu $menu, array $options = []): string
    {
        $rootItems = $this->menuItemRepository->findRootItemsByMenu($menu);

        if (empty($rootItems)) {
            return '';
        }

        $attributes = $options['attributes'] ?? [];
        $attributes['class'] = trim(($attributes['class'] ?? '') . ' navbar-nav me-auto');

        return $this->renderItems($rootItems, 0, $attributes);
    }

    public function renderMenuItem(MenuItem $item, array $options = []): string
    {
        $children = array_filter(
            $item->getChildren()->toArray(),
            fn(MenuItem $child) => $this->authorizationChecker->isGranted($child)
        );

        if (!$this->authorizationChecker->isGranted($item)) {
            return '';
        }

        $hasChildren = !empty($children);
        $level = $options['level'] ?? 0;
        $isDropdown = $hasChildren && $level === 0;
        $isSubDropdown = $hasChildren && $level > 0;

        $link = $this->generateLink($item);
        $iconClass = $item->getIcon();
        if ($iconClass && !preg_match('/^fa(s|r|l|d|b)\s/', $iconClass)) {
            $iconClass = 'fas ' . $iconClass;
        }
        $icon = $iconClass ? '<i class="' . $iconClass . ' me-1"></i>' : '';
        $cssClass = $item->getCssClass() ? ' ' . $item->getCssClass() : '';

        $html = '<li class="nav-item' . ($isDropdown ? ' dropdown' : '') . ($isSubDropdown ? ' dropdown-submenu' : '') . '">';

        if ($isDropdown) {
            $html .= '<a class="nav-link dropdown-toggle' . $cssClass . '" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">' . $icon . $item->getLabel() . '</a>';
            $html .= '<ul class="dropdown-menu">';
        } elseif ($isSubDropdown) {
            $html .= '<a class="dropdown-item dropdown-toggle' . $cssClass . '" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">' . $icon . $item->getLabel() . '</a>';
            $html .= '<ul class="dropdown-menu">';
        } else {
            $target = $item->isOpenInNewTab() ? ' target="_blank"' : '';
            $rel = $item->isOpenInNewTab() ? ' rel="noopener noreferrer"' : '';
            $html .= '<a class="' . ($level === 0 ? 'nav-link' : 'dropdown-item') . $cssClass . '" href="' . $link . '"' . $target . $rel . '>' . $icon . $item->getLabel() . '</a>';
        }

        if ($hasChildren) {
            foreach ($children as $child) {
                $html .= $this->renderMenuItem($child, ['level' => $level + 1]);
            }
            $html .= '</ul>';
        }

        $html .= '</li>';

        return $html;
    }

    private function renderItems(array $items, int $level, array $attributes = []): string
    {
        $html = '<ul ' . $this->renderAttributes($attributes) . '>';

        foreach ($items as $item) {
            if ($this->authorizationChecker->isGranted($item)) {
                $html .= $this->renderMenuItem($item, ['level' => $level]);
            }
        }

        $html .= '</ul>';

        return $html;
    }

    private function generateLink(MenuItem $item): string
    {
        $link = $item->getLink();

        if (empty($link)) {
            return '#';
        }

        // Check if it's a route name (no / or http)
        if (str_starts_with($link, '/') || str_starts_with($link, 'http') || str_starts_with($link, '#')) {
            return $link;
        }

        // Try to generate route URL
        try {
            return $this->urlGenerator->generate($link);
        } catch (\Exception) {
            return $link;
        }
    }

    private function renderAttributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $key => $value) {
            $html .= ' ' . $key . '="' . htmlspecialchars((string) $value, ENT_QUOTES) . '"';
        }
        return $html;
    }
}