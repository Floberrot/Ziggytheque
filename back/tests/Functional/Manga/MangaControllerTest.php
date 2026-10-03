<?php

declare(strict_types=1);

namespace App\Tests\Functional\Manga;

use App\Tests\Functional\AbstractApiTestCase;
use App\Tests\Functional\Fixtures\HandTypedSeriesTrait;

final class MangaControllerTest extends AbstractApiTestCase
{
    use HandTypedSeriesTrait;

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function importManga(array $overrides = []): string
    {
        $payload  = array_merge([
            'title'        => 'One Piece',
            'language'     => 'fr',
            'edition'      => null,
            'author'       => 'Oda Eiichiro',
            'summary'      => 'A pirate story.',
            'coverUrl'     => null,
            'genre'        => 'shonen',
            'externalId'   => null,
            'totalVolumes' => null,
        ], $overrides);

        $response = $this->jsonRequest('POST', '/api/manga', $payload);
        $this->assertSame(201, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        return (string) $data['id'];
    }

    // ── POST /api/manga ──────────────────────────────────────────────────────

    public function testImportMangaMinimal(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga', [
            'title'    => 'Minimal Manga',
            'language' => 'fr',
        ]);
        $data = $this->assertJsonStatus(201, $response);

        $this->assertArrayHasKey('id', $data);
        $this->assertIsString($data['id']);
    }

    public function testImportMangaWithVolumes(): void
    {
        $series = $this->collectHandTypedSeries('Fullmetal Alchemist', 3, ['genre' => 'shonen']);

        $detail = $this->collectionDetail($series['entryId']);
        $this->assertSame('Fullmetal Alchemist', $detail['manga']['title']);
        $this->assertSame('shonen', $detail['manga']['genre']);
        $this->assertSame([1, 2, 3], array_column($detail['volumes'], 'number'));
    }

    public function testImportMangaRequiresTitle(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga', ['language' => 'fr']);
        $this->assertSame(422, $response->getStatusCode());
    }

    public function testImportMangaRefusesAnAbsurdTomeCount(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga', ['title' => 'X', 'language' => 'fr', 'totalVolumes' => 100000]);

        $this->assertJsonStatus(422, $response);
    }

    public function testImportMangaRefusesAnUnknownGenre(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga', ['title' => 'X', 'language' => 'fr', 'genre' => 'bogus']);

        $this->assertJsonStatus(422, $response);
    }

    public function testImportMangaRefusesACoverThatIsNoWebUrl(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga', [
            'title' => 'X', 'language' => 'fr', 'coverUrl' => 'javascript:alert(1)',
        ]);

        $this->assertJsonStatus(422, $response);
    }

    public function testImportMangaRequiresAuth(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga', ['title' => 'X', 'language' => 'fr'], auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    // ── PATCH /api/manga/{id} ────────────────────────────────────────────────

    public function testUpdateManga(): void
    {
        $series = $this->collectHandTypedSeries('Old Title');

        $response = $this->jsonRequest('PATCH', '/api/manga/' . $series['mangaId'], ['title' => 'New Title']);
        $this->assertSame(204, $response->getStatusCode());

        $this->assertSame('New Title', $this->collectionDetail($series['entryId'])['manga']['title']);
    }

    public function testImportMangaWithSpecialEdition(): void
    {
        $series = $this->collectHandTypedSeries('Berserk', 1, ['edition' => 'Glénat', 'specialEdition' => 'Prestige']);

        $manga = $this->collectionDetail($series['entryId'])['manga'];
        $this->assertSame('Glénat', $manga['edition']);
        $this->assertSame('Prestige', $manga['specialEdition']);
    }

    public function testImportMangaRejectsTooLongSpecialEdition(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga', [
            'title'          => 'Berserk',
            'language'       => 'fr',
            'specialEdition' => str_repeat('x', 151),
        ]);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function testUpdateMangaSetsAndClearsTheSpecialEdition(): void
    {
        ['mangaId' => $id, 'entryId' => $entryId] = $this->collectHandTypedSeries('One Piece');

        $this->assertSame(204, $this->jsonRequest('PATCH', '/api/manga/' . $id, ['specialEdition' => 'Perfect edition'])->getStatusCode());
        $this->assertSame('Perfect edition', $this->collectionDetail($entryId)['manga']['specialEdition']);

        $this->assertSame(204, $this->jsonRequest('PATCH', '/api/manga/' . $id, ['specialEdition' => ''])->getStatusCode());
        $this->assertNull($this->collectionDetail($entryId)['manga']['specialEdition']);
    }

    public function testUpdateMangaRejectsTooLongSpecialEdition(): void
    {
        $id = $this->importManga();

        $response = $this->jsonRequest('PATCH', '/api/manga/' . $id, ['specialEdition' => str_repeat('x', 151)]);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function testUpdateMangaNotFound(): void
    {
        $response = $this->jsonRequest('PATCH', '/api/manga/bad-id', ['title' => 'T']);
        $this->assertJsonStatus(404, $response);
    }

    public function testUpdateMangaRequiresAuth(): void
    {
        $response = $this->jsonRequest('PATCH', '/api/manga/any-id', ['title' => 'T'], auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    // ── PATCH /api/manga/{id}/volumes/{volumeId} ──────────────────────────────

    public function testUpdateVolume(): void
    {
        ['mangaId' => $mangaId, 'entryId' => $entryId, 'volumeIds' => [$volumeId]] = $this->collectHandTypedSeries('One Piece');

        $response = $this->jsonRequest(
            'PATCH',
            '/api/manga/' . $mangaId . '/volumes/' . $volumeId,
            ['price' => 7.99, 'isbn' => '9782811645632', 'coverUrl' => 'https://covers.example/1.jpg'],
        );
        $this->assertSame(204, $response->getStatusCode());

        $volume = $this->collectionDetail($entryId)['volumes'][0];
        $this->assertSame('9782811645632', $volume['isbn']);
        $this->assertSame(7.99, $volume['price']);
        $this->assertSame('https://covers.example/1.jpg', $volume['coverUrl']);
    }

    public function testUpdateVolumeRefusesAnInvalidReleaseDate(): void
    {
        ['mangaId' => $mangaId, 'volumeIds' => [$volumeId]] = $this->collectHandTypedSeries('One Piece');

        $response = $this->jsonRequest('PATCH', '/api/manga/' . $mangaId . '/volumes/' . $volumeId, ['releaseDate' => '31/02/2026']);

        $this->assertJsonStatus(422, $response);
    }

    public function testUpdateVolumeWithIsbnAlone(): void
    {
        ['mangaId' => $mangaId, 'entryId' => $entryId, 'volumeIds' => [$volumeId]] = $this->collectHandTypedSeries('One Piece');

        // The ISBN is the only field of the PATCH — the auto-save flow of the front
        $response = $this->jsonRequest(
            'PATCH',
            '/api/manga/' . $mangaId . '/volumes/' . $volumeId,
            ['isbn' => '978-2-8116-4563-2'],
        );
        $this->assertSame(204, $response->getStatusCode());

        $this->assertSame('9782811645632', $this->collectionDetail($entryId)['volumes'][0]['isbn']);
    }

    public function testUpdateVolumeConvertsIsbn10ToIsbn13(): void
    {
        ['mangaId' => $mangaId, 'entryId' => $entryId, 'volumeIds' => [$volumeId]] = $this->collectHandTypedSeries('One Piece');

        // 2723425487 is a checksum-valid ISBN-10 — stored as its ISBN-13 form
        $response = $this->jsonRequest(
            'PATCH',
            '/api/manga/' . $mangaId . '/volumes/' . $volumeId,
            ['isbn' => '2723425487'],
        );
        $this->assertSame(204, $response->getStatusCode());

        $this->assertSame('9782723425483', $this->collectionDetail($entryId)['volumes'][0]['isbn']);
    }

    public function testUpdateVolumeRejectsInvalidIsbn(): void
    {
        ['mangaId' => $mangaId, 'volumeIds' => [$volumeId]] = $this->collectHandTypedSeries('One Piece');

        $response = $this->jsonRequest(
            'PATCH',
            '/api/manga/' . $mangaId . '/volumes/' . $volumeId,
            ['isbn' => 'xxx'],
        );
        $this->assertSame(422, $response->getStatusCode());
    }

    public function testUpdateVolumeOfAnUnknownSeriesReturns404(): void
    {
        $response = $this->jsonRequest('PATCH', '/api/manga/bad-id/volumes/bad-vol', ['price' => 5.0]);
        $this->assertJsonStatus(404, $response);
    }

    public function testUpdateVolumeRequiresAuth(): void
    {
        $response = $this->jsonRequest('PATCH', '/api/manga/any-id/volumes/any-vol', ['price' => 5.0], auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testUpdateVolumeNotFound(): void
    {
        $mangaId  = $this->importManga();
        $response = $this->jsonRequest('PATCH', '/api/manga/' . $mangaId . '/volumes/bad-vol', ['price' => 5.0]);
        $this->assertJsonStatus(404, $response);
    }

    // ── GET /api/manga/volume-search ─────────────────────────────────────────

    public function testVolumeSearchReturnsEmpty(): void
    {
        // NullMangaCoverApiClient is active in test env — always returns []
        $response = $this->jsonRequest('GET', '/api/manga/volume-search?q=test');
        $data     = $this->assertJsonStatus(200, $response);
        $this->assertSame([], $data);
    }

    // ── POST /api/manga/{id}/auto-covers ─────────────────────────────────────

    public function testAutoCoversRequiresAuth(): void
    {
        $id = $this->importManga();

        $response = $this->jsonRequest('POST', '/api/manga/' . $id . '/auto-covers', [], auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testAutoCoversNotFound(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga/nonexistent-id/auto-covers', ['force' => false]);
        $this->assertJsonStatus(404, $response);
    }

    public function testAutoCoversReturns202AndDispatchesAsyncMessage(): void
    {
        $id = $this->importManga(['title' => 'Stub Manga', 'totalVolumes' => 3]);

        $response = $this->jsonRequest('POST', '/api/manga/' . $id . '/auto-covers', ['force' => false]);
        $data = $this->assertJsonStatus(202, $response);

        $this->assertArrayHasKey('batchId', $data);
        $this->assertArrayHasKey('mercureUrl', $data);
        $this->assertArrayHasKey('subscriberToken', $data);
        $this->assertArrayHasKey('topic', $data);
        $this->assertStringContainsString($data['batchId'], $data['topic']);

        /** @var \Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $messages = $transport->getSent();
        $this->assertCount(1, $messages);
        $message = $messages[0]->getMessage();
        $this->assertInstanceOf(\App\Manga\Application\AutoCovers\AutoCoversBatchMessage::class, $message);
        $this->assertSame($id, $message->mangaId);
        $this->assertSame($data['batchId'], $message->batchId);
    }

    public function testAutoCoversAsyncHandlerPublishesProgressEvents(): void
    {
        $id = $this->importManga(['title' => 'Progress Manga', 'totalVolumes' => 2]);

        $this->jsonRequest('POST', '/api/manga/' . $id . '/auto-covers', ['force' => false]);

        /** @var \Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        /** @var \Symfony\Component\Messenger\MessageBusInterface $messageBus */
        $messageBus = static::getContainer()->get(\Symfony\Component\Messenger\MessageBusInterface::class);

        foreach ($transport->getSent() as $envelope) {
            $messageBus->dispatch($envelope->getMessage(), [
                new \Symfony\Component\Messenger\Stamp\ReceivedStamp('async'),
                new \Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp(),
            ]);
        }

        /** @var \App\Tests\Doubles\Manga\InMemoryCoverBatchProgressPublisher $publisher */
        $publisher = static::getContainer()->get(\App\Tests\Doubles\Manga\InMemoryCoverBatchProgressPublisher::class);
        $types = array_map(static fn ($event) => $event->type, $publisher->events);

        $this->assertSame('batch_started', $types[0]);
        $this->assertSame('batch_completed', end($types));

        $completedEvent = $publisher->events[count($publisher->events) - 1];
        $this->assertSame(2, $completedEvent->failed);
        $this->assertSame(0, $completedEvent->resolved);
    }

    // ── POST /api/manga/translate-summary ─────────────────────────────────────

    public function testTranslateSummaryRequiresAuth(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga/translate-summary', ['text' => 'Pirates.'], auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testTranslateSummaryReturnsTranslatedText(): void
    {
        // The test translator (NullSummaryTranslator) echoes the input back, so the
        // assertion proves the endpoint → query bus → handler → translator wiring.
        $response = $this->jsonRequest('POST', '/api/manga/translate-summary', ['text' => 'A pirate story.']);
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertArrayHasKey('translated', $data);
        $this->assertSame('A pirate story.', $data['translated']);
    }

    public function testTranslateSummaryRejectsATextTooLongForTheTranslator(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga/translate-summary', ['text' => str_repeat('a', 5001)]);
        $this->assertJsonStatus(422, $response);
    }

    public function testTranslateSummaryAcceptsTheLongestText(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga/translate-summary', ['text' => str_repeat('a', 5000)]);
        $this->assertJsonStatus(200, $response);
    }

    public function testTranslateSummaryRejectsBlankText(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga/translate-summary', ['text' => '']);
        $this->assertSame(422, $response->getStatusCode());
    }
}
