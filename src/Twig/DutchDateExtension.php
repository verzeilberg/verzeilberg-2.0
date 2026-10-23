<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\CoreExtension;
use Twig\TwigFilter;

/**
 * Like Twig's |date filter, but with Dutch day and month names (l, D, F and M).
 */
class DutchDateExtension extends AbstractExtension
{
    private const DAYS = ['maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag'];
    private const DAYS_SHORT = ['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'];
    private const MONTHS = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
    private const MONTHS_SHORT = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];

    public function getFilters(): array
    {
        return [
            new TwigFilter('nl_date', [$this, 'formatDate'], ['needs_environment' => true]),
        ];
    }

    public function formatDate(Environment $env, mixed $date, string $format): string
    {
        // Same conversion and timezone handling as |date
        $date = $env->getExtension(CoreExtension::class)->convertDate($date);

        $result = '';
        $length = strlen($format);
        for ($i = 0; $i < $length; $i++) {
            $char = $format[$i];
            if ($char === '\\' && $i + 1 < $length) {
                $result .= $format[++$i];
                continue;
            }
            $result .= match ($char) {
                'l' => self::DAYS[(int) $date->format('N') - 1],
                'D' => self::DAYS_SHORT[(int) $date->format('N') - 1],
                'F' => self::MONTHS[(int) $date->format('n') - 1],
                'M' => self::MONTHS_SHORT[(int) $date->format('n') - 1],
                default => $date->format($char),
            };
        }

        return $result;
    }
}
