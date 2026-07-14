<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure\Validator;

use App\Manga\Infrastructure\Validator\ValidIsbn;
use App\Manga\Infrastructure\Validator\ValidIsbnValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/** @extends ConstraintValidatorTestCase<ValidIsbnValidator> */
final class ValidIsbnValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ValidIsbnValidator
    {
        return new ValidIsbnValidator();
    }

    public function testNullIsSkipped(): void
    {
        $this->validator->validate(null, new ValidIsbn());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsSkipped(): void
    {
        $this->validator->validate('', new ValidIsbn());

        $this->assertNoViolation();
    }

    public function testValidIsbn13Passes(): void
    {
        $this->validator->validate('9782723425483', new ValidIsbn());

        $this->assertNoViolation();
    }

    public function testValidIsbn10WithHyphensPasses(): void
    {
        $this->validator->validate('2-7234-2548-7', new ValidIsbn());

        $this->assertNoViolation();
    }

    public function testIsbn13WithEanAddOnPasses(): void
    {
        $this->validator->validate('978272342548312345', new ValidIsbn());

        $this->assertNoViolation();
    }

    public function testInvalidIsbnRaisesOneViolation(): void
    {
        $constraint = new ValidIsbn();

        $this->validator->validate('not-an-isbn', $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testWrongChecksumRaisesOneViolation(): void
    {
        $constraint = new ValidIsbn();

        $this->validator->validate('9782723425484', $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testNonStringValueIsRejected(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(12345, new ValidIsbn());
    }
}
