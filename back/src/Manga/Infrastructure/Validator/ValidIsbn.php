<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Validates a request field as an ISBN-10 or ISBN-13 (separators and EAN add-on
 * tolerated) via {@see \App\Manga\Domain\Isbn}. Null / blank values are skipped.
 *
 * Used on request DTOs so an invalid ISBN fails the payload mapping with a 422
 * violation scoped to the field, before any command reaches the transaction.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class ValidIsbn extends Constraint
{
    public string $message = 'This value is not a valid ISBN-10 or ISBN-13.';
}
