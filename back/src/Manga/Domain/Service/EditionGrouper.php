<?php

declare(strict_types=1);

namespace App\Manga\Domain\Service;

use App\Manga\Domain\ExternalEditionDto;

/**
 * @phpstan-type RawVolume array{
 *     publisher: string|null,
 *     year: int|null,
 *     volumeNumber: int|null,
 *     title: string,
 *     coverUrl: string|null,
 *     isbn: string|null
 * }
 * @phpstan-type GroupedVolume array{
 *     publisher: string,
 *     year: int|null,
 *     volumeNumber: int|null,
 *     title: string,
 *     coverUrl: string|null,
 *     isbn: string|null
 * }
 */
final readonly class EditionGrouper
{
    private const array VARIANT_KEYWORDS = [
        'perfect', 'deluxe', 'originale', 'double', 'collector', 'ultimate',
    ];

    /**
     * Groups raw volume data by publisher into ExternalEditionDto instances.
     *
     * @param array<int, RawVolume> $rawVolumes
     * @return ExternalEditionDto[]
     */
    public function group(array $rawVolumes, string $language, string $source = 'grouped'): array
    {
        /** @var array<string, array<int, GroupedVolume>> $byPublisher */
        $byPublisher = [];

        foreach ($rawVolumes as $rawVolume) {
            if (empty($rawVolume['publisher'])) {
                continue;
            }

            $normalizedPublisher = trim((string) $rawVolume['publisher']);
            if ($normalizedPublisher === '') {
                continue;
            }

            $groupKey = strtolower($normalizedPublisher);
            $byPublisher[$groupKey][] = array_merge($rawVolume, ['publisher' => $normalizedPublisher]);
        }

        $editions = [];

        foreach ($byPublisher as $groupKey => $volumeItems) {
            $publisherName = $volumeItems[0]['publisher'];

            $year = $this->resolveMinYear($volumeItems);
            $coverUrl = $this->resolveCoverUrl($volumeItems);
            $volumeCount = $this->resolveVolumeCount($volumeItems);
            $sampleIsbn = $this->resolveSampleIsbn($volumeItems);
            $editionLabel = $this->resolveEditionLabel($volumeItems);

            $editions[] = new ExternalEditionDto(
                publisher: $publisherName,
                editionLabel: $editionLabel,
                year: $year,
                language: $language,
                coverUrl: $coverUrl,
                volumeCount: $volumeCount,
                sampleIsbn: $sampleIsbn,
                source: $source,
            );
        }

        return $editions;
    }

    /**
     * @param array<int, GroupedVolume> $volumeItems
     */
    private function resolveMinYear(array $volumeItems): ?int
    {
        $years = array_filter(array_column($volumeItems, 'year'));

        if (empty($years)) {
            return null;
        }

        return (int) min($years);
    }

    /**
     * @param array<int, GroupedVolume> $volumeItems
     */
    private function resolveCoverUrl(array $volumeItems): ?string
    {
        // Prefer cover from volume number 1
        foreach ($volumeItems as $volumeItem) {
            if ($volumeItem['volumeNumber'] === 1 && $volumeItem['coverUrl'] !== null) {
                return $volumeItem['coverUrl'];
            }
        }

        // Fall back to smallest volume number with a cover
        $sortedByNumber = $volumeItems;
        usort($sortedByNumber, static function (array $firstItem, array $secondItem): int {
            $firstNumber  = $firstItem['volumeNumber'] ?? PHP_INT_MAX;
            $secondNumber = $secondItem['volumeNumber'] ?? PHP_INT_MAX;
            return $firstNumber <=> $secondNumber;
        });

        foreach ($sortedByNumber as $volumeItem) {
            if ($volumeItem['coverUrl'] !== null) {
                return $volumeItem['coverUrl'];
            }
        }

        // Fall back to any item with a cover
        foreach ($volumeItems as $volumeItem) {
            if ($volumeItem['coverUrl'] !== null) {
                return $volumeItem['coverUrl'];
            }
        }

        return null;
    }

    /**
     * @param array<int, GroupedVolume> $volumeItems
     */
    private function resolveVolumeCount(array $volumeItems): int
    {
        $volumeNumbers = array_filter(array_column($volumeItems, 'volumeNumber'));

        if (!empty($volumeNumbers)) {
            return (int) max($volumeNumbers);
        }

        return count($volumeItems);
    }

    /**
     * @param array<int, GroupedVolume> $volumeItems
     */
    private function resolveSampleIsbn(array $volumeItems): ?string
    {
        foreach ($volumeItems as $volumeItem) {
            if ($volumeItem['isbn'] !== null && $volumeItem['isbn'] !== '') {
                return $volumeItem['isbn'];
            }
        }

        return null;
    }

    /**
     * @param array<int, GroupedVolume> $volumeItems
     */
    private function resolveEditionLabel(array $volumeItems): ?string
    {
        foreach ($volumeItems as $volumeItem) {
            $titleLower = strtolower($volumeItem['title']);
            foreach (self::VARIANT_KEYWORDS as $keyword) {
                if (str_contains($titleLower, $keyword)) {
                    return $keyword;
                }
            }
        }

        return null;
    }
}
