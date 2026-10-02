<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain\Exception;

use App\Manga\Domain\Exception\CatalogueUnavailableException;
use App\Manga\Domain\Exception\TooManyVolumesException;
use App\Manga\Domain\Exception\VolumeAlreadyExistsException;
use PHPUnit\Framework\TestCase;

final class VolumeLimitExceptionsTest extends TestCase
{
    public function testTooManyVolumesNamesTheBoundAndAnswers422(): void
    {
        $exception = new TooManyVolumesException(100000);

        $this->assertStringContainsString('500', $exception->getMessage());
        $this->assertStringContainsString('100000', $exception->getMessage());
        $this->assertSame(422, $exception->getHttpStatusCode());
    }

    public function testVolumeAlreadyExistsAnswers409(): void
    {
        $exception = new VolumeAlreadyExistsException(3);

        $this->assertStringContainsString('3', $exception->getMessage());
        $this->assertSame(409, $exception->getHttpStatusCode());
    }

    public function testCatalogueUnavailableNamesTheSourceAndAnswers503(): void
    {
        $exception = new CatalogueUnavailableException('BnF');

        $this->assertStringContainsString('BnF', $exception->getMessage());
        $this->assertSame(503, $exception->getHttpStatusCode());
    }
}
