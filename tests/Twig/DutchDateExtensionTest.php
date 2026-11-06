<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\DutchDateExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Loader\ArrayLoader;

/** |nl_date works like |date, with Dutch day and month names */
class DutchDateExtensionTest extends TestCase
{
    /** @return iterable<string, array{0: mixed, 1: string, 2: string}> */
    public static function dates(): iterable
    {
        yield 'full names' => [self::date('2026-11-04'), 'l j F Y', 'woensdag 4 november 2026'];
        yield 'short names' => [self::date('2026-03-01'), 'D j M', 'zo 1 mrt'];
        yield 'numbers stay as |date' => [self::date('2026-03-01 14:05'), 'd-m-Y H:i', '01-03-2026 14:05'];
        yield 'escaped letters are literal' => [self::date('2026-05-18'), 'l \o\m H\u', 'maandag om 00u'];
        yield 'string date' => ['2026-12-25', 'l F', 'vrijdag december'];
        yield 'every month' => [self::date('2026-08-10'), 'F M', 'augustus aug'];
    }

    #[DataProvider('dates')]
    public function testFormatsInDutch(mixed $date, string $format, string $expected): void
    {
        $twig = new Environment(new ArrayLoader(['t' => '{{ date|nl_date(format) }}']));
        $twig->getExtension(CoreExtension::class)->setTimezone('Europe/Amsterdam');
        $twig->addExtension(new DutchDateExtension());

        $this->assertSame($expected, $twig->render('t', ['date' => $date, 'format' => $format]));
    }

    private static function date(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date, new \DateTimeZone('Europe/Amsterdam'));
    }

    public function testUsesTheTimezoneOfTheDateFilter(): void
    {
        $twig = new Environment(new ArrayLoader(['t' => '{{ date|nl_date("l H:i") }}']));
        $twig->getExtension(CoreExtension::class)->setTimezone('Europe/Amsterdam');
        $twig->addExtension(new DutchDateExtension());

        // 23:30 UTC on a Sunday is 00:30 on Monday in Amsterdam (winter time)
        $this->assertSame('maandag 00:30', $twig->render('t', ['date' => new \DateTimeImmutable('2026-01-04 23:30', new \DateTimeZone('UTC'))]));
    }
}
