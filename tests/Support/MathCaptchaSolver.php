<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\DomCrawler\Crawler;

/** Reads the sum from the contact form's captcha label ("Hoeveel is drie plus vier?") and answers it */
final class MathCaptchaSolver
{
    private const NUMBERS = ['een' => 1, 'twee' => 2, 'drie' => 3, 'vier' => 4, 'vijf' => 5, 'zes' => 6, 'zeven' => 7, 'acht' => 8, 'negen' => 9, 'tien' => 10];

    public static function answer(Crawler $crawler): string
    {
        $label = $crawler->filter('label[for="contact_captcha"]')->text();
        if (!preg_match('/Hoeveel is (\w+) plus (\w+)\?/', $label, $m)) {
            throw new \RuntimeException('No sum in captcha label: ' . $label);
        }

        return (string) (self::NUMBERS[$m[1]] + self::NUMBERS[$m[2]]);
    }
}
