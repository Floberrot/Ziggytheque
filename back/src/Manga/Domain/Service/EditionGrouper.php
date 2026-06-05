<?php

declare(strict_types=1);

namespace App\Manga\Domain\Service;

use App\Manga\Domain\ExternalEditionDto;

final readonly class EditionGrouper
{
    public function __construct(private PublisherNormalizer $publisherNormalizer)
    {
    }

    /**
     * Deduplicates a flat list of edition DTOs by (canonical publisher, language,
     * edition line, format), merging best cover / isbn sample and keeping the maximum
     * volume count. Publishers are normalised first, so "Glénat (Grenoble)" and
     * "Glénat" — or the five Viz aliases — collapse into one entry, and each entry is
     * relabelled with its clean publisher + edition line.
     *
     * Returns a sorted list: by country (FR first, then US, then others), then by label.
     *
     * @param  list<ExternalEditionDto> $dtos
     * @return list<ExternalEditionDto>
     */
    public function group(array $dtos): array
    {
        /** @var array<string, ExternalEditionDto> $grouped */
        $grouped = [];

        foreach ($dtos as $dto) {
            $normalized = $this->relabel($dto);
            $key        = $this->keyFor($normalized);

            if (!array_key_exists($key, $grouped)) {
                $grouped[$key] = $normalized;
                continue;
            }

            $existing      = $grouped[$key];
            $grouped[$key] = new ExternalEditionDto(
                workTitle:    $existing->workTitle,
                editionLabel: $existing->editionLabel,
                publisher:    $existing->publisher,
                language:     $existing->language,
                country:      $existing->country,
                format:       $existing->format,
                volumeCount:  $this->maxVolumeCount($existing->volumeCount, $normalized->volumeCount),
                isbnSample:   $existing->isbnSample ?? $normalized->isbnSample,
                coverUrl:     $existing->coverUrl ?? $normalized->coverUrl,
                source:       $existing->source,
                externalId:   $existing->externalId ?? $normalized->externalId,
                editionLine:  $existing->editionLine,
            );
        }

        $editions = array_values($grouped);

        usort($editions, static function (ExternalEditionDto $alpha, ExternalEditionDto $bravo): int {
            $countryOrder = ['FR' => 0, 'US' => 1];
            $orderAlpha = $countryOrder[$alpha->country ?? ''] ?? 99;
            $orderBravo = $countryOrder[$bravo->country ?? ''] ?? 99;

            if ($orderAlpha !== $orderBravo) {
                return $orderAlpha <=> $orderBravo;
            }

            return strcmp($alpha->editionLabel, $bravo->editionLabel);
        });

        return $editions;
    }

    /** Replaces the raw publisher + label with the canonical publisher and a clean label. */
    private function relabel(ExternalEditionDto $dto): ExternalEditionDto
    {
        $publisher = $this->publisherNormalizer->displayName($dto->publisher);

        return new ExternalEditionDto(
            workTitle:    $dto->workTitle,
            editionLabel: $this->buildLabel($publisher, $dto->editionLine, $dto->workTitle),
            publisher:    $publisher,
            language:     $dto->language,
            country:      $dto->country,
            format:       $dto->format,
            volumeCount:  $dto->volumeCount,
            isbnSample:   $dto->isbnSample,
            coverUrl:     $dto->coverUrl,
            source:       $dto->source,
            externalId:   $dto->externalId,
            editionLine:  $dto->editionLine,
        );
    }

    private function buildLabel(?string $publisher, ?string $editionLine, string $workTitle): string
    {
        if ($editionLine !== null) {
            return ($publisher ?? $workTitle) . ' — ' . $editionLine;
        }

        return $publisher ?? $workTitle;
    }

    private function keyFor(ExternalEditionDto $dto): string
    {
        return implode('|', [
            $this->publisherNormalizer->canonicalKey($dto->publisher),
            $dto->language,
            mb_strtolower($dto->editionLine ?? ''),
            $dto->format->value,
        ]);
    }

    private function maxVolumeCount(?int $alpha, ?int $bravo): ?int
    {
        if ($alpha === null) {
            return $bravo;
        }

        if ($bravo === null) {
            return $alpha;
        }

        return max($alpha, $bravo);
    }
}
