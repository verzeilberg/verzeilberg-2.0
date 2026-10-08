<?php

declare(strict_types=1);

namespace App\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class NoOverlappingSeasons extends Constraint
{
    public string $message = 'Er bestaat al een seizoen dat overlapt met deze data.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}