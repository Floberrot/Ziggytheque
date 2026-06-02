<?php

declare(strict_types=1);

namespace App\Tests\Functional\Manga;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaVolumeCoverDto;
use App\Tests\Doubles\Manga\StubMangaCoverProvider;
use App\Tests\Functional\AbstractApiTestCase;

final class CoverByIsbnControllerTest extends AbstractApiTestCase
{
    private StubMangaCoverProvider $coverProvider;

    protected function setUp(): void
    {
        parent::setUp();
        // Keep the same kernel/container across requests so the stub instance we
        // configure here is the one the handler resolves during the HTTP request.
        // Without this, KernelBrowser reboots the kernel and the registration is lost.
        $this->client->disableReboot();
        /** @var StubMangaCoverProvider $provider */
        $provider = static::getContainer()->get(StubMangaCoverProvider::class);
        $this->coverProvider = $provider;
    }

    public function testCoverByIsbnRequiresAuth(): void
    {
        $response = $this->jsonRequest('GET', '/api/manga/cover-by-isbn?isbn=9782811645632', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testCoverByIsbnReturnsEmptyArrayWhenNoSourceHasCover(): void
    {
        $response = $this->jsonRequest('GET', '/api/manga/cover-by-isbn?isbn=9782723492607');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('[]', (string) $response->getContent());
    }

    public function testCoverByIsbnReturns422ForInvalidIsbn(): void
    {
        $response = $this->jsonRequest('GET', '/api/manga/cover-by-isbn?isbn=not-an-isbn');
        $this->assertSame(422, $response->getStatusCode());
    }

    public function testCoverByIsbnGroupsCoversFromEverySource(): void
    {
        $isbn = '9782811645632';
        $this->coverProvider->registerIsbn($isbn, new MangaVolumeCoverDto(
            coverUrl: 'https://bnf.example/cover.jpg',
            spineUrl: null,
            isbn: Isbn::fromString($isbn),
            source: 'bnf',
        ));
        $this->coverProvider->registerIsbn($isbn, new MangaVolumeCoverDto(
            coverUrl: 'https://google.example/cover.jpg',
            spineUrl: null,
            isbn: Isbn::fromString($isbn),
            source: 'google_books',
        ));

        $response = $this->jsonRequest('GET', '/api/manga/cover-by-isbn?isbn=' . $isbn);
        $data = $this->assertJsonStatus(200, $response);

        $this->assertCount(2, $data);
        $this->assertSame('bnf', $data[0]['source']);
        $this->assertSame('https://bnf.example/cover.jpg', $data[0]['coverUrl']);
        $this->assertSame('google_books', $data[1]['source']);
        $this->assertSame($isbn, $data[1]['isbn']);
    }
}
