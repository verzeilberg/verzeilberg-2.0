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
            // {{ 5288|run_time }}: a race time or pace, "1:28:08" or "5:27"
            new TwigFilter('run_time', [$this, 'runTime']),
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

    public function runTime(int|float $seconds): string
    {
        $seconds = (int) round($seconds);
        $hours = intdiv($seconds, 3600);

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, intdiv($seconds % 3600, 60), $seconds % 60)
            : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
