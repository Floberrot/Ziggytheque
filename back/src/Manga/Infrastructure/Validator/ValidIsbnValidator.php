<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Validator;

use App\Manga\Domain\Isbn;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ValidIsbnValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidIsbn) {
            throw new UnexpectedTypeException($constraint, ValidIsbn::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (Isbn::tryFrom($value) === null) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
