<?php

declare(strict_types=1);

namespace App\Service\Steam;

/**
 * What a library sync changed; "pending" is what is left for the next run because of the per-run limits.
 */
final class SteamSyncResult
{
    public function __construct(
        public readonly int $added,
        public readonly int $updated,
        public readonly int $removed,
        public readonly int $details,
        public readonly int $achievements,
        public readonly int $detailsPending,
        public readonly int $achievementsPending,
    ) {
    }

    public function summary(): string
    {
        $summary = sprintf(
            '%d toegevoegd, %d bijgewerkt, %d verwijderd; winkelgegevens voor %d game(s), prestaties voor %d game(s).',
            $this->added, $this->updated, $this->removed, $this->details, $this->achievements
        );
        if ($this->detailsPending > 0 || $this->achievementsPending > 0) {
            $summary .= sprintf(' Volgende keer: nog %d game(s) voor gegevens en %d voor prestaties.', $this->detailsPending, $this->achievementsPending);
        }

        return $summary;
    }
}
