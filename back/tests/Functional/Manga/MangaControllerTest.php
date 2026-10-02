<?php

declare(strict_types=1);

namespace App\Tests\Functional\Manga;

use App\Tests\Functional\AbstractApiTestCase;

final class MangaControllerTest extends AbstractApiTestCase
{
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

    // ── GET /api/manga ───────────────────────────────────────────────────────

    public function testSearchRequiresAuth(): void
    {
        $response = $this->jsonRequest('GET', '/api/manga', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testSearchReturnsEmptyList(): void
    {
        $response = $this->jsonRequest('GET', '/api/manga');
        $data     = $this->assertJsonStatus(200, $response);
        $this->assertIsArray($data);
    }

    public function testSearchByQuery(): void
    {
        $this->importManga(['title' => 'Naruto']);
        $this->importManga(['title' => 'Bleach']);

        $response = $this->jsonRequest('GET', '/api/manga?q=Naruto');
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertIsArray($data);
        $titles = array_column($data, 'title');
        $this->assertContains('Naruto', $titles);
    }

    // ── GET /api/manga/{id} ──────────────────────────────────────────────────

    public function testGetMangaById(): void
    {
        $id       = $this->importManga(['title' => 'Dragon Ball']);
        $response = $this->jsonRequest('GET', '/api/manga/' . $id);
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertSame($id, $data['id']);
        $this->assertSame('Dragon Ball', $data['title']);
        $this->assertArrayHasKey('volumes', $data);
    }

    public function testGetMangaNotFound(): void
    {
        $response = $this->jsonRequest('GET', '/api/manga/nonexistent-id');
        $this->assertJsonStatus(404, $response);
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
        $response = $this->jsonRequest('POST', '/api/manga', [
            'title'        => 'Fullmetal Alchemist',
            'language'     => 'fr',
            'genre'        => 'shonen',
            'totalVolumes' => 3,
        ]);
        $data = $this->assertJsonStatus(201, $response);
        $id   = $data['id'];

        $detail = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/manga/' . $id));
        $this->assertCount(3, $detail['volumes']);
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
        $id = $this->importManga(['title' => 'Old Title']);

        $response = $this->jsonRequest('PATCH', '/api/manga/' . $id, ['title' => 'New Title']);
        $this->assertSame(204, $response->getStatusCode());

        $detail = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/manga/' . $id));
        $this->assertSame('New Title', $detail['title']);
    }

    public function testImportMangaWithSpecialEdition(): void
    {
        $id = $this->importManga(['edition' => 'Glénat', 'specialEdition' => 'Prestige']);

        $detail = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/manga/' . $id));
        $this->assertSame('Glénat', $detail['edition']);
        $this->assertSame('Prestige', $detail['specialEdition']);
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
        $id = $this->importManga();

        $this->assertSame(204, $this->jsonRequest('PATCH', '/api/manga/' . $id, ['specialEdition' => 'Perfect edition'])->getStatusCode());
        $detail = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/manga/' . $id));
        $this->assertSame('Perfect edition', $detail['specialEdition']);

        $this->assertSame(204, $this->jsonRequest('PATCH', '/api/manga/' . $id, ['specialEdition' => ''])->getStatusCode());
        $detail = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/manga/' . $id));
        $this->assertNull($detail['specialEdition']);
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

    // ── POST /api/manga/{id}/volumes ─────────────────────────────────────────

    public function testAddVolume(): void
    {
        $id = $this->importManga();

        $response = $this->jsonRequest('POST', '/api/manga/' . $id . '/volumes', [
            'number'      => 1,
            'coverUrl'    => null,
            'releaseDate' => null,
        ]);
        $data = $this->assertJsonStatus(201, $response);

        $this->assertArrayHasKey('id', $data);
    }

    public function testAddingAnExistingTomeNumberReturns409(): void
    {
        $id = $this->importManga();
        $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/manga/' . $id . '/volumes', ['number' => 1]));

        $this->assertJsonStatus(409, $this->jsonRequest('POST', '/api/manga/' . $id . '/volumes', ['number' => 1]));
    }

    public function testAddVolumeRefusesAnInvalidReleaseDate(): void
    {
        $id = $this->importManga();

        $response = $this->jsonRequest('POST', '/api/manga/' . $id . '/volumes', ['number' => 1, 'releaseDate' => 'soon']);

        $this->assertJsonStatus(422, $response);
    }

    public function testAddVolumeToNonExistentManga(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga/bad-id/volumes', ['number' => 1]);
        $this->assertJsonStatus(404, $response);
    }

    // ── PATCH /api/manga/{id}/volumes/{volumeId} ──────────────────────────────

    public function testUpdateVolume(): void
    {
        $mangaId = $this->importManga();

        $volData = $this->assertJsonStatus(201, $this->jsonRequest(
            'POST',
            '/api/manga/' . $mangaId . '/volumes',
            ['number' => 1],
        ));
        $volumeId = $volData['id'];

        $response = $this->jsonRequest(
            'PATCH',
            '/api/manga/' . $mangaId . '/volumes/' . $volumeId,
            ['price' => 7.99, 'isbn' => '9782811645632'],
        );
        $this->assertSame(204, $response->getStatusCode());

        $detail = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/manga/' . $mangaId));
        $volume = $detail['volumes'][0];
        $this->assertSame('9782811645632', $volume['isbn']);
    }

    public function testUpdateVolumeRefusesAnInvalidReleaseDate(): void
    {
        $mangaId  = $this->importManga();
        $volumeId = $this->assertJsonStatus(201, $this->jsonRequest(
            'POST',
            '/api/manga/' . $mangaId . '/volumes',
            ['number' => 1],
        ))['id'];

        $response = $this->jsonRequest('PATCH', '/api/manga/' . $mangaId . '/volumes/' . $volumeId, ['releaseDate' => '31/02/2026']);

        $this->assertJsonStatus(422, $response);
    }

    public function testUpdateVolumeWithIsbnAlone(): void
    {
        $mangaId = $this->importManga();

        $volData = $this->assertJsonStatus(201, $this->jsonRequest(
            'POST',
            '/api/manga/' . $mangaId . '/volumes',
            ['number' => 1],
        ));
        $volumeId = $volData['id'];

        // The ISBN is the only field of the PATCH — the auto-save flow of the front
        $response = $this->jsonRequest(
            'PATCH',
            '/api/manga/' . $mangaId . '/volumes/' . $volumeId,
            ['isbn' => '978-2-8116-4563-2'],
        );
        $this->assertSame(204, $response->getStatusCode());

        $detail = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/manga/' . $mangaId));
        $volume = $detail['volumes'][0];
        $this->assertSame('9782811645632', $volume['isbn']);
    }

    public function testUpdateVolumeConvertsIsbn10ToIsbn13(): void
    {
        $mangaId = $this->importManga();

        $volData = $this->assertJsonStatus(201, $this->jsonRequest(
            'POST',
            '/api/manga/' . $mangaId . '/volumes',
            ['number' => 1],
        ));
        $volumeId = $volData['id'];

        // 2723425487 is a checksum-valid ISBN-10 — stored as its ISBN-13 form
        $response = $this->jsonRequest(
            'PATCH',
            '/api/manga/' . $mangaId . '/volumes/' . $volumeId,
            ['isbn' => '2723425487'],
        );
        $this->assertSame(204, $response->getStatusCode());

        $detail = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/manga/' . $mangaId));
        $this->assertSame('9782723425483', $detail['volumes'][0]['isbn']);
    }

    public function testUpdateVolumeRejectsInvalidIsbn(): void
    {
        $mangaId = $this->importManga();

        $volData = $this->assertJsonStatus(201, $this->jsonRequest(
            'POST',
            '/api/manga/' . $mangaId . '/volumes',
            ['number' => 1],
        ));
        $volumeId = $volData['id'];

        $response = $this->jsonRequest(
            'PATCH',
            '/api/manga/' . $mangaId . '/volumes/' . $volumeId,
            ['isbn' => 'xxx'],
        );
        $this->assertSame(422, $response->getStatusCode());
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

    public function testTranslateSummaryRejectsBlankText(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga/translate-summary', ['text' => '']);
        $this->assertSame(422, $response->getStatusCode());
    }
}
