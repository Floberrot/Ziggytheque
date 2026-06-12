<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\EditionFormatEnum;
use App\Manga\Domain\EditionProviderInterface;
use App\Manga\Domain\ExternalEditionDto;
use App\Manga\Infrastructure\ExternalApi\CompositeEditionProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class CompositeEditionProviderTest extends TestCase
{
    private function makeDto(string $source): ExternalEditionDto
    {
        return new ExternalEditionDto(
            workTitle:    'Berserk',
            editionLabel: 'Glénat — Berserk',
            publisher:    'Glénat',
            language:     'fr',
            country:      'FR',
            format:       EditionFormatEnum::Broche,
            volumeCount:  null,
            isbnSample:   null,
            coverUrl:     null,
            source:       $source,
        );
    }

    /** @param list<ExternalEditionDto> $editions */
    private function makeProvider(array $editions): EditionProviderInterface
    {
        return new class ($editions) implements EditionProviderInterface {
            /** @param list<ExternalEditionDto> $editions */
            public function __construct(private readonly array $editions)
            {
            }

            public function findEditions(string $workTitle, ?string $author, ?string $language): array
            {
                return $this->editions;
            }
        };
    }

    private function throwingProvider(): EditionProviderInterface
    {
        return new class implements EditionProviderInterface {
            public function findEditions(string $workTitle, ?string $author, ?string $language): array
            {
                throw new RuntimeException('upstream error');
            }
        };
    }

    public function testMergesResultsFromAllProviders(): void
    {
        $dtoA = $this->makeDto('bnf');
        $dtoB = $this->makeDto('open_library');

        $composite = new CompositeEditionProvider(
            [$this->makeProvider([$dtoA]), $this->makeProvider([$dtoB])],
            new NullLogger(),
        );

        $result = $composite->findEditions('Berserk', null, null);

        $this->assertSame([$dtoA, $dtoB], $result);
    }

    public function testSkipsFailingProviderWithoutException(): void
    {
        $dto = $this->makeDto('bnf');

        $composite = new CompositeEditionProvider(
            [$this->throwingProvider(), $this->makeProvider([$dto])],
            new NullLogger(),
        );

        $result = $composite->findEditions('Berserk', null, null);

        $this->assertSame([$dto], $result);
    }

    public function testReturnsEmptyArrayWhenAllProvidersFail(): void
    {
        $composite = new CompositeEditionProvider(
            [$this->throwingProvider(), $this->throwingProvider()],
            new NullLogger(),
        );

        $result = $composite->findEditions('Berserk', null, null);

        $this->assertSame([], $result);
    }

    public function testReturnsEmptyArrayWhenNoProviders(): void
    {
        $composite = new CompositeEditionProvider([], new NullLogger());

        $this->assertSame([], $composite->findEditions('Berserk', null, null));
    }
}
