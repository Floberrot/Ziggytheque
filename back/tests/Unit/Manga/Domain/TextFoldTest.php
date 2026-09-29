<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\TextFold;
use PHPUnit\Framework\TestCase;

final class TextFoldTest extends TestCase
{
    public function testFoldsCaseAccentsAndPunctuation(): void
    {
        $this->assertSame('l attaque des titans', TextFold::fold("L'Attaque   des Titans !"));
        $this->assertSame('edition coeur', TextFold::fold('Édition Cœur'));
        $this->assertSame('kaiju n 8', TextFold::fold('Kaiju n°8'));
    }

    public function testFoldsNullAndBlankToAnEmptyString(): void
    {
        $this->assertSame('', TextFold::fold(null));
        $this->assertSame('', TextFold::fold(' - '));
    }
}
