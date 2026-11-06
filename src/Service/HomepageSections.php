<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Which blocks the home page shows. Each block is a boolean setting "home_show_<section>";
 * a block without a setting is shown, so the home page looks the same until something is switched off.
 */
class HomepageSections
{
    /** section => [label, description] */
    public const SECTIONS = [
        'blog' => ['Nieuwste berichten', 'Het uitgelichte bericht en de drie berichten daarna'],
        'event' => ['Volgend event', 'Het eerstvolgende event met aftelklok'],
        'games' => ['Recent gespeeld', 'De laatst gespeelde Steam-games (volgt ook de Steam-privacyinstelling)'],
    ];

    public function __construct(
        private SettingRepository $settings,
        private EntityManagerInterface $em,
    ) {
    }

    public static function settingKey(string $section): string
    {
        return 'home_show_' . $section;
    }

    public function isVisible(string $section): bool
    {
        $this->assertKnown($section);

        return $this->settings->getBooleanValue(self::settingKey($section), true);
    }

    /** @return array<string, bool> section => visible, for every section */
    public function all(): array
    {
        $visible = [];
        foreach (array_keys(self::SECTIONS) as $section) {
            $visible[$section] = $this->isVisible($section);
        }

        return $visible;
    }

    public function setVisible(string $section, bool $visible): void
    {
        $this->assertKnown($section);
        $setting = $this->settings->setValue(self::settingKey($section), $visible ? '1' : '0');
        $setting->setType('boolean')->setDescription('Startpagina: toon het blok "' . self::SECTIONS[$section][0] . '"');
        $this->em->flush();
    }

    private function assertKnown(string $section): void
    {
        if (!isset(self::SECTIONS[$section])) {
            throw new \InvalidArgumentException(sprintf('Unknown home page section "%s".', $section));
        }
    }
}
