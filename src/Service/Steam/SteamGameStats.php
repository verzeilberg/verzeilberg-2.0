<?php

declare(strict_types=1);

namespace App\Service\Steam;

/**
 * The statistics of one game for the admin game page: the player's achievements (with global rarity)
 * and the game's own stats, as SteamGameStatsProvider collected them.
 */
final class SteamGameStats
{
    /** Steam gives the player's achievements */
    public const STATUS_OK = 'ok';
    /** The game has no achievements */
    public const STATUS_NONE = 'none';
    /** Steam keeps the player's progress private (game marked private) */
    public const STATUS_PRIVATE = 'private';
    /** Steam couldn't be asked; see $errors */
    public const STATUS_ERROR = 'error';

    /**
     * @param list<array{apiName: string, name: string, description: ?string, icon: ?string, hidden: bool, achieved: bool, unlockedAt: ?\DateTimeImmutable, globalPercent: ?float}> $achievements
     *        unlocked first (most recent first), then locked (most common first)
     * @param list<array{name: string, value: float|int}> $stats
     * @param list<string> $errors
     */
    public function __construct(
        public readonly string $status,
        public readonly array $achievements,
        public readonly array $stats,
        public readonly array $errors,
        public readonly \DateTimeImmutable $fetchedAt,
    ) {
    }

    public function getTotal(): int
    {
        return count($this->achievements);
    }

    public function getUnlocked(): int
    {
        return count(array_filter($this->achievements, fn (array $achievement) => $achievement['achieved']));
    }

    public function getPercentage(): int
    {
        return $this->getTotal() > 0 ? (int) floor(100 * $this->getUnlocked() / $this->getTotal()) : 0;
    }

    /** @return array{apiName: string, name: string, description: ?string, icon: ?string, hidden: bool, achieved: bool, unlockedAt: ?\DateTimeImmutable, globalPercent: ?float}|null the unlocked achievement the fewest players have */
    public function getRarestUnlocked(): ?array
    {
        $rarest = null;
        foreach ($this->achievements as $achievement) {
            if ($achievement['achieved'] && $achievement['globalPercent'] !== null && ($rarest === null || $achievement['globalPercent'] < $rarest['globalPercent'])) {
                $rarest = $achievement;
            }
        }

        return $rarest;
    }

    /**
     * @param 'all'|'unlocked'|'locked' $show
     *
     * @return list<array{apiName: string, name: string, description: ?string, icon: ?string, hidden: bool, achieved: bool, unlockedAt: ?\DateTimeImmutable, globalPercent: ?float}>
     */
    public function filter(string $show): array
    {
        return match ($show) {
            'unlocked' => array_values(array_filter($this->achievements, fn (array $achievement) => $achievement['achieved'])),
            'locked' => array_values(array_filter($this->achievements, fn (array $achievement) => !$achievement['achieved'])),
            default => $this->achievements,
        };
    }
}
