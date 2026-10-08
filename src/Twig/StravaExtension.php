<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class StravaExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('format_time', [$this, 'formatTime']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('format_time', [$this, 'formatTime']),
        ];
    }

    public function formatTime(int $seconds): string
    {
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);
        
        if ($hours > 0) {
            return sprintf('%du %02dm', $hours, $minutes);
        }
        
        return sprintf('%dm', $minutes);
    }
}