<?php

declare(strict_types=1);

namespace App\Tests\Functional\Fixtures;

/**
 * The "À la main" flow of the front: a series typed by hand, then put in the
 * collection, whose detail is the only place the front reads a series back.
 *
 * @phpstan-require-extends \App\Tests\Functional\AbstractApiTestCase
 */
trait HandTypedSeriesTrait
{
    /**
     * @param  array<string, mixed> $fields extra POST /api/manga fields (edition, specialEdition…)
     * @return array{mangaId: string, entryId: string, volumeIds: list<string>}
     */
    private function collectHandTypedSeries(string $title, int $tomes = 1, array $fields = []): array
    {
        $manga = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/manga', array_merge(
            ['title' => $title, 'language' => 'fr', 'totalVolumes' => $tomes],
            $fields,
        )));
        $entry = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/collection', [
            'mangaId' => $manga['id'],
        ]));

        return [
            'mangaId'   => (string) $manga['id'],
            'entryId'   => (string) $entry['id'],
            'volumeIds' => array_map('strval', array_column($this->collectionDetail((string) $entry['id'])['volumes'], 'volumeId')),
        ];
    }

    /** @return array<string, mixed> GET /api/collection/{id}: the series (`manga`) and its tomes (`volumes`) */
    private function collectionDetail(string $entryId): array
    {
        return $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/collection/' . $entryId));
    }
}
