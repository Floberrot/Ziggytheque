<?php

declare(strict_types=1);

namespace App\Manga\Domain\Service;

use App\Manga\Domain\ExternalEditionDto;
use App\Manga\Domain\Isbn;

final readonly class EditionGrouper
{
    public function __construct(private PublisherNormalizer $publisherNormalizer)
    {
    }

    /**
     * Deduplicates a flat list of edition DTOs by (publisher imprint, language, edition
     * line, format), merging best cover / isbn sample and keeping the maximum volume
     * count. Publishers are normalised first, so "Glénat (Grenoble)" and "Glénat" — or
     * the five Viz aliases — collapse into one entry, and each entry is relabelled with
     * its clean publisher + edition line.
     *
     * Two guards keep genuinely distinct editions apart:
     * - the grouping key uses {@see PublisherNormalizer::imprintKey()}, so imprints that
     *   merely share a display name (Tonkam vs Delcourt) do not merge;
     * - inside one key, records whose ISBN registration groups disagree (a 978-2 French
     *   run vs a 978-4 Japanese one mislabelled with the same language) stay separate.
     *
     * Returns a sorted list: by country (FR first, then US, then others), then by label.
     *
     * @param  list<ExternalEditionDto> $dtos
     * @return list<ExternalEditionDto>
     */
    public function group(array $dtos): array
    {
        /** @var array<string, non-empty-list<ExternalEditionDto>> $buckets */
        $buckets = [];
        foreach ($dtos as $dto) {
            // Key from the RAW publisher: relabel() collapses imprints into one display
            // name (Delcourt/Tonkam), which must not leak into the grouping key.
            $buckets[$this->keyFor($dto)][] = $this->relabel($dto);
        }

        $editions = [];
        foreach ($buckets as $bucket) {
            foreach ($this->partitionByIsbnGroup($bucket) as $partition) {
                $editions[] = $this->mergePartition($partition);
            }
        }

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

    /**
     * Splits one key bucket by ISBN registration group (digit after the 978/979 prefix:
     * 0/1 English, 2 French, 3 German, 4 Japanese…). Records without an ISBN carry no
     * evidence and never force a split: they join the first partition.
     *
     * @param  non-empty-list<ExternalEditionDto> $bucket
     * @return non-empty-list<non-empty-list<ExternalEditionDto>>
     */
    private function partitionByIsbnGroup(array $bucket): array
    {
        /** @var list<non-empty-list<ExternalEditionDto>> $partitions */
        $partitions = [];
        /** @var array<string, int> $partitionIndexByGroup */
        $partitionIndexByGroup = [];
        $unclaimedIndex        = null;

        foreach ($bucket as $dto) {
            $registrationGroup = $this->isbnRegistrationGroup($dto->isbnSample);

            if ($registrationGroup === null) {
                if ($partitions === []) {
                    $partitions[]   = [$dto];
                    $unclaimedIndex = 0;
                    continue;
                }
                $partitions[0][] = $dto;
                continue;
            }

            if (isset($partitionIndexByGroup[$registrationGroup])) {
                $partitions[$partitionIndexByGroup[$registrationGroup]][] = $dto;
                continue;
            }

            if ($unclaimedIndex !== null) {
                $partitions[$unclaimedIndex][]                  = $dto;
                $partitionIndexByGroup[$registrationGroup]      = $unclaimedIndex;
                $unclaimedIndex                                 = null;
                continue;
            }

            $partitions[]                              = [$dto];
            $partitionIndexByGroup[$registrationGroup] = count($partitions) - 1;
        }

        return $partitions;
    }

    /**
     * ISBN registration group as a coarse market signal. Groups 0 and 1 are both the
     * English-speaking area, so they map to one value — a US line printing under both
     * must not be split in two.
     */
    private function isbnRegistrationGroup(?string $isbnSample): ?string
    {
        $isbn = Isbn::tryFrom($isbnSample);
        if ($isbn === null) {
            return null;
        }

        $group = $isbn->groupIdentifier();

        return ($group === '0' || $group === '1') ? '0/1' : $group;
    }

    /** @param non-empty-list<ExternalEditionDto> $partition */
    private function mergePartition(array $partition): ExternalEditionDto
    {
        $merged = $partition[0];

        foreach (array_slice($partition, 1) as $candidate) {
            $merged = new ExternalEditionDto(
                workTitle:    $merged->workTitle,
                editionLabel: $merged->editionLabel,
                publisher:    $merged->publisher,
                language:     $merged->language,
                country:      $merged->country,
                format:       $merged->format,
                volumeCount:  $this->maxVolumeCount($merged->volumeCount, $candidate->volumeCount),
                isbnSample:   $merged->isbnSample ?? $candidate->isbnSample,
                coverUrl:     $merged->coverUrl ?? $candidate->coverUrl,
                source:       $merged->source,
                externalId:   $merged->externalId ?? $candidate->externalId,
                editionLine:  $merged->editionLine,
            );
        }

        return $merged;
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
            $this->publisherNormalizer->imprintKey($dto->publisher),
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
