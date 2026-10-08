<?php

declare(strict_types=1);

namespace App\Validator\Constraint;

use App\Repository\Handbal\HandbalSeizoenRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class NoOverlappingSeasonsValidator extends ConstraintValidator
{
    public function __construct(
        private HandbalSeizoenRepository $seizoenRepository
    ) {}

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoOverlappingSeasons) {
            throw new UnexpectedTypeException($constraint, NoOverlappingSeasons::class);
        }

        if (!$value instanceof \App\Entity\Handbal\HandbalSeizoen) {
            return;
        }

        $overlappingSeizoenen = $this->seizoenRepository->findOverlapping(
            $value->getStartDatum(),
            $value->getEindDatum(),
            $value->getId()
        );

        if (!empty($overlappingSeizoenen)) {
            $this->context->buildViolation($constraint->message)
                ->atPath('startDatum')
                ->addViolation();
        }
    }
}