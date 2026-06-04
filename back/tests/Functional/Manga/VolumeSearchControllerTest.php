<?php

declare(strict_types=1);

namespace App\Tests\Functional\Manga;

use App\Manga\Domain\MangaVolumeCoverDto;
use App\Tests\Doubles\Manga\StubMangaCoverProvider;
use App\Tests\Functional\AbstractApiTestCase;

final class VolumeSearchControllerTest extends AbstractApiTestCase
{
    private StubMangaCoverProvider $coverProvider;

    protected function setUp(): void
    {
        parent::setUp();
        // Keep the same kernel/container across requests so the stub instance we
        // configure here is the one the handler resolves during the HTTP request.
        $this->client->disableReboot();
        /** @var StubMangaCoverProvider $provider */
        $provider = static::getContainer()->get(StubMangaCoverProvider::class);
        $this->coverProvider = $provider;
    }

    public function testVolumeSearchRequiresAuth(): void
    {
        $response = $this->jsonRequest('GET', '/api/manga/volume-search?q=One+Piece', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testVolumeSearchReturnsEmptyArrayWhenNoSourceHasCovers(): void
    {
        $response = $this->jsonRequest('GET', '/api/manga/volume-search?q=One+Piece');
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertSame([], $data);
    }

    public function testVolumeSearchMergesCoversFromEverySourceWithProvenance(): void
    {
        $this->coverProvider->registerContext(new MangaVolumeCoverDto(
            coverUrl: 'https://mangadex.example/cover.jpg',
            spineUrl: null,
            isbn: null,
            source: 'mangadex',
        ));
        $this->coverProvider->registerContext(new MangaVolumeCoverDto(
            coverUrl: 'https://google.example/cover.jpg',
            spineUrl: null,
            isbn: null,
            source: 'google_books',
        ));

        $response = $this->jsonRequest('GET', '/api/manga/volume-search?q=One+Piece&volumeNumber=1');
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertCount(2, $data);
        $this->assertSame('mangadex', $data[0]['source']);
        $this->assertSame('https://mangadex.example/cover.jpg', $data[0]['coverUrl']);
        $this->assertSame('google_books', $data[1]['source']);
        $this->assertSame('One Piece', $data[1]['title']);
    }
}
