<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Outcome of a checklist import: either items were imported, or nothing was imported and $errors says why.
 */
final readonly class CheckListImportResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public int $imported = 0,
        public array $errors = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->errors === [];
    }
}
