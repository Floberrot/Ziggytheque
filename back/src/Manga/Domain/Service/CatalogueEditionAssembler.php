<?php

declare(strict_types=1);

namespace App\Manga\Domain\Service;

use App\Manga\Domain\Catalogue\CatalogueEdition;
use App\Manga\Domain\Catalogue\CatalogueRecord;
use App\Manga\Domain\Catalogue\CatalogueVolume;
use App\Manga\Domain\EditionIdentity;
use App\Shared\Domain\Text\TextFold;

/**
 * Rebuilds series from individual volume records: one edition per (work, publisher,
 * special edition). The special edition is DISCOVERED from the records themselves:
 *
 * - the qualifier written between the work and the number ("Berserk : prestige. 3")
 *   is edition-level by structure;
 * - a qualifier written after the number ("Berserk - Tome 3 - Édition prestige") is
 *   only an edition name when it repeats on several volumes of the same work and
 *   publisher — a volume title changes from one tome to the next, an edition name
 *   does not.
 */
final readonly class CatalogueEditionAssembler
{
    private const int MAX_EDITIONS = 40;

    public function __construct(private PublisherNormalizer $publisherNormalizer)
    {
    }

    /**
     * @param  list<CatalogueRecord> $records
     * @return list<CatalogueEdition> works matching the query first, then each work's
     *                                standard run before its special editions
     */
    public function assemble(array $records, ?string $query = null): array
    {
        $sharedTrailingQualifiers = $this->sharedTrailingQualifiers($records);

        /** @var array<string, non-empty-list<array{record: CatalogueRecord, specialEdition: string|null}>> $groups */
        $groups = [];
        foreach ($records as $record) {
            $specialEdition = $record->headQualifier
                ?? $this->sharedTrailingQualifier($record, $sharedTrailingQualifiers);

            $identity = new EditionIdentity($record->workTitle, $record->publisher, $specialEdition);
            $groups[$identity->key($this->publisherNormalizer)][] = [
                'record'         => $record,
                'specialEdition' => $specialEdition,
            ];
        }

        $editions = array_map(
            fn (array $group): CatalogueEdition => $this->buildEdition($group),
            array_values($groups),
        );

        $foldedQuery = TextFold::fold($query);
        usort(
            $editions,
            static function (CatalogueEdition $left, CatalogueEdition $right) use ($foldedQuery): int {
                return [
                    self::queryRank($left, $foldedQuery),
                    TextFold::fold($left->workTitle),
                    $left->specialEdition === null ? 0 : 1,
                    -$left->volumeCount,
                    TextFold::fold($left->specialEdition),
                ] <=> [
                    self::queryRank($right, $foldedQuery),
                    TextFold::fold($right->workTitle),
                    $right->specialEdition === null ? 0 : 1,
                    -$right->volumeCount,
                    TextFold::fold($right->specialEdition),
                ];
            },
        );

        return array_slice($editions, 0, self::MAX_EDITIONS);
    }

    /** 0 = the work is exactly what was searched, 1 = starts with it, 2 = anything else. */
    private static function queryRank(CatalogueEdition $edition, string $foldedQuery): int
    {
        $foldedWork = TextFold::fold($edition->workTitle);

        return match (true) {
            $foldedQuery === '' || $foldedWork === $foldedQuery => 0,
            str_starts_with($foldedWork, $foldedQuery), str_starts_with($foldedQuery, $foldedWork) => 1,
            default => 2,
        };
    }

    /**
     * Trailing qualifiers seen on at least two different volume numbers of the same
     * work and publisher, keyed "work|publisher" → folded qualifiers.
     *
     * @param  list<CatalogueRecord> $records
     * @return array<string, array<string, true>>
     */
    private function sharedTrailingQualifiers(array $records): array
    {
        /** @var array<string, array<string, array<int, true>>> $volumesByQualifier */
        $volumesByQualifier = [];
        foreach ($records as $record) {
            if ($record->trailingQualifier === null || $record->volumeNumber === null) {
                continue;
            }

            $seriesKey = $this->seriesKey($record);
            $volumesByQualifier[$seriesKey][TextFold::fold($record->trailingQualifier)][$record->volumeNumber] = true;
        }

        $shared = [];
        foreach ($volumesByQualifier as $seriesKey => $qualifiers) {
            foreach ($qualifiers as $foldedQualifier => $volumeNumbers) {
                if (count($volumeNumbers) >= 2) {
                    $shared[$seriesKey][$foldedQualifier] = true;
                }
            }
        }

        return $shared;
    }

    /** @param array<string, array<string, true>> $sharedTrailingQualifiers */
    private function sharedTrailingQualifier(CatalogueRecord $record, array $sharedTrailingQualifiers): ?string
    {
        if ($record->trailingQualifier === null) {
            return null;
        }

        $isShared = isset(
            $sharedTrailingQualifiers[$this->seriesKey($record)][TextFold::fold($record->trailingQualifier)],
        );

        return $isShared ? $record->trailingQualifier : null;
    }

    private function seriesKey(CatalogueRecord $record): string
    {
        return TextFold::fold($record->workTitle) . '|' . $this->publisherNormalizer->imprintKey($record->publisher);
    }

    /** @param non-empty-list<array{record: CatalogueRecord, specialEdition: string|null}> $group */
    private function buildEdition(array $group): CatalogueEdition
    {
        $first = $group[0]['record'];

        /** @var array<int, CatalogueVolume> $volumesByNumber */
        $volumesByNumber = [];
        $author = null;
        foreach ($group as $member) {
            $record = $member['record'];
            $author ??= $record->author;
            if ($record->volumeNumber === null) {
                continue;
            }

            $volumesByNumber[$record->volumeNumber] = $this->preferredVolume(
                $volumesByNumber[$record->volumeNumber] ?? null,
                new CatalogueVolume($record->volumeNumber, $record->isbn, $record->coverUrl),
            );
        }

        // A one-shot (artbook, guide, single-volume edition) carries no number.
        if ($volumesByNumber === []) {
            $volumesByNumber[1] = new CatalogueVolume(1, $first->isbn, $first->coverUrl);
        }

        ksort($volumesByNumber);
        $volumes = array_values($volumesByNumber);

        $coverUrl = null;
        foreach ($volumes as $volume) {
            if ($volume->coverUrl !== null) {
                $coverUrl = $volume->coverUrl;
                break;
            }
        }

        return new CatalogueEdition(
            workTitle: $first->workTitle,
            publisher: $this->publisherNormalizer->displayName($first->publisher),
            specialEdition: $group[0]['specialEdition'],
            author: $author,
            coverUrl: $coverUrl,
            volumeCount: (int) max(array_keys($volumesByNumber)),
            volumes: $volumes,
        );
    }

    /** Two records for the same tome: keep the one with an ISBN, then the one with a cover. */
    private function preferredVolume(?CatalogueVolume $current, CatalogueVolume $candidate): CatalogueVolume
    {
        if ($current === null) {
            return $candidate;
        }

        return new CatalogueVolume(
            number: $current->number,
            isbn: $current->isbn ?? $candidate->isbn,
            coverUrl: $current->coverUrl ?? $candidate->coverUrl,
        );
    }
}
