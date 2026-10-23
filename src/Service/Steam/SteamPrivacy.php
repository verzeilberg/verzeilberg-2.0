<?php

declare(strict_types=1);

namespace App\Service\Steam;

use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Whether the public pages (/games, home page) show when games were played: the "Playing …" label,
 * "Recently played", last played dates and the playtime of the last two weeks. The admin always shows everything.
 */
class SteamPrivacy
{
    public const SETTING = 'steam_hide_activity';

    public function __construct(
        private SettingRepository $settings,
        private EntityManagerInterface $em,
    ) {
    }

    public function isActivityHidden(): bool
    {
        return $this->settings->getBooleanValue(self::SETTING);
    }

    public function setActivityHidden(bool $hidden): void
    {
        $setting = $this->settings->setValue(self::SETTING, $hidden ? '1' : '0');
        $setting->setType('boolean')->setDescription('Steam: verberg op de openbare pagina\'s wanneer games zijn gespeeld (laatst gespeeld, recent gespeeld, nu aan het spelen)');
        $this->em->flush();
    }
}
