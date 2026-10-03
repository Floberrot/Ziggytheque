<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaCoverProviderInterface;
use App\Manga\Domain\MangaVolumeCoverDto;
use App\Manga\Domain\MultiContextCoverProviderInterface;
use App\Manga\Infrastructure\ExternalApi\BnfCoversApiClient;
use App\Manga\Infrastructure\ExternalApi\CompositeMangaCoverApiClient;
use App\Manga\Infrastructure\ExternalApi\DeferredContextCoverProviderInterface;
use App\Manga\Infrastructure\ExternalApi\DeferredIsbnCoverProviderInterface;
use App\Manga\Infrastructure\ExternalApi\GoogleBooksDynamicLinksApiClient;
use App\Manga\Infrastructure\ExternalApi\GoogleBooksMangaApiClient;
use App\Manga\Infrastructure\ExternalApi\HardcoverCoversApiClient;
use App\Manga\Infrastructure\ExternalApi\MangaDexMangaApiClient;
use App\Manga\Infrastructure\ExternalApi\OpenLibraryCoversApiClient;
use App\Tests\Doubles\Http\RecordingResponseFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * Cover-by-ISBN and the volume search ask every cover source at the same time: every
 * lookup is sent before any answer is read, the covers come back in the sources' order,
 * and a source failing or timing out never costs the others theirs. The first-hit
 * cascade (auto-covers) still asks one source after another.
 */
final class ConcurrentCoverLookupTest extends TestCase
{
    private const string ISBN = '9782723492539';
    private const string ARK = 'ark:/12148/cb45365380x';

    private RecordingResponseFactory $upstream;

    protected function setUp(): void
    {
        $image = str_repeat('i', 2500);

        $this->upstream = (new RecordingResponseFactory())
            ->answer('catalogue.bnf.fr/api/SRU', 'bnf-record', '<record>' . self::ARK . '</record>')
            ->answer('catalogue.bnf.fr/couverture', 'bnf-cover', $image, [
                'response_headers' => ['content-type' => 'image/jpeg'],
            ])
            ->answer('covers.openlibrary.org/b/isbn/', 'open-library', $image, [
                'response_headers' => ['content-length' => '2500'],
            ])
            ->answer('books.google.com/books?bibkeys', 'google-books', sprintf(
                'gbcb({"ISBN:%s":{"thumbnail_url":"http://books.google.com/books/content?id=g1&zoom=5&edge=curl"}});',
                self::ISBN,
            ))
            ->answer('api.hardcover.app', 'hardcover', (string) json_encode([
                'data' => ['editions' => [['image' => ['url' => 'https://assets.hardcover.app/berserk-3.jpg']]]],
            ]))
            ->answer('api.mangadex.org/manga', 'mangadex-search', (string) json_encode([
                'data' => [['id' => 'series-1', 'attributes' => ['title' => ['en' => 'Berserk'], 'altTitles' => []]]],
            ]))
            ->answer('api.mangadex.org/cover', 'mangadex-covers', (string) json_encode([
                'data' => [['attributes' => ['volume' => '3', 'fileName' => 'berserk-3.jpg', 'locale' => 'ja']]],
                'total' => 1,
            ]))
            ->answer('googleapis.com/books/v1/volumes', 'google-books-search', (string) json_encode([
                'items' => [[
                    'id' => 'g3',
                    'volumeInfo' => [
                        'title' => 'Berserk T03',
                        'categories' => ['Comics & Graphic Novels / Manga'],
                        'imageLinks' => ['thumbnail' => 'http://books.google.com/books/content?id=g3&edge=curl'],
                    ],
                ]],
            ]));
    }

    public function testCoverByIsbnSendsEveryLookupBeforeReadingAny(): void
    {
        $this->composite()->findAllByIsbn(Isbn::fromString(self::ISBN));

        $this->assertTrue($this->upstream->sentBeforeAnyRead('bnf-record', 'open-library', 'google-books', 'hardcover'));
        // The BnF's second step (the cover itself) needs its record first.
        $this->assertSame(
            ['bnf-record', 'open-library', 'google-books', 'hardcover', 'bnf-cover'],
            $this->upstream->sentSources(),
        );
    }

    public function testCoverByIsbnKeepsTheSourcesOrder(): void
    {
        $covers = $this->composite()->findAllByIsbn(Isbn::fromString(self::ISBN));

        $this->assertSame(
            ['bnf', 'open_library', 'google_books', 'hardcover'],
            array_map(static fn (MangaVolumeCoverDto $cover): string => $cover->source, $covers),
        );
    }

    public function testCoverByIsbnFindsTheSameCoversAsAskingTheSourcesOneByOne(): void
    {
        $concurrentCovers = $this->composite()->findAllByIsbn(Isbn::fromString(self::ISBN));

        $sequentialCovers = [];
        foreach ($this->isbnProviders() as $provider) {
            $cover = $provider->findByIsbn(Isbn::fromString(self::ISBN));
            if ($cover !== null) {
                $sequentialCovers[] = $cover;
            }
        }

        $this->assertEquals($sequentialCovers, $concurrentCovers);
    }

    public function testASourceThatTimesOutOnlyLosesItsOwnCover(): void
    {
        $this->upstream->answer('covers.openlibrary.org/b/isbn/', 'open-library', '', ['error' => 'Idle timeout reached']);

        $covers = $this->composite()->findAllByIsbn(Isbn::fromString(self::ISBN));

        $this->assertSame(
            ['bnf', 'google_books', 'hardcover'],
            array_map(static fn (MangaVolumeCoverDto $cover): string => $cover->source, $covers),
        );
    }

    public function testASourceWhoseLookupCannotBeSentOnlyLosesItsOwnCover(): void
    {
        $this->upstream->refuse('catalogue.bnf.fr/api/SRU', 'bnf-record');

        $covers = $this->composite()->findAllByIsbn(Isbn::fromString(self::ISBN));

        $this->assertSame(
            ['open_library', 'google_books', 'hardcover'],
            array_map(static fn (MangaVolumeCoverDto $cover): string => $cover->source, $covers),
        );
    }

    public function testVolumeSearchSendsEverySearchBeforeReadingAny(): void
    {
        $covers = $this->composite()->findAllByContext('Berserk', null, 3);

        $this->assertTrue($this->upstream->sentBeforeAnyRead('mangadex-search', 'google-books-search'));
        $this->assertSame(['mangadex-search', 'google-books-search', 'mangadex-covers'], $this->upstream->sentSources());
        $this->assertSame(
            [
                'https://uploads.mangadex.org/covers/series-1/berserk-3.jpg',
                'https://books.google.com/books/content?id=g3',
            ],
            array_map(static fn (MangaVolumeCoverDto $cover): string => $cover->coverUrl, $covers),
        );
    }

    public function testVolumeSearchKeepsTheOtherSourceWhenOneFails(): void
    {
        $this->upstream->answer('api.mangadex.org/manga', 'mangadex-search', '', ['http_code' => 503]);

        $covers = $this->composite()->findAllByContext('Berserk', null, 3);

        $this->assertSame(
            ['google_books'],
            array_map(static fn (MangaVolumeCoverDto $cover): string => $cover->source, $covers),
        );
    }

    /** First hit wins: the next sources are never asked (their calls are rate-limited). */
    public function testTheFirstHitCascadeStillAsksOneSourceAfterAnother(): void
    {
        $cover = $this->composite()->findByIsbn(Isbn::fromString(self::ISBN));

        $this->assertNotNull($cover);
        $this->assertSame('bnf', $cover->source);
        $this->assertSame(['bnf-record', 'bnf-cover'], $this->upstream->sentSources());
    }

    /** Each source swallows its own failure — sending or reading — and finds nothing. */
    public function testEverySourceFindsNothingWhenItsCallCannotBeSentOrTimesOut(): void
    {
        $refusedEverywhere = (new RecordingResponseFactory())->refuse('https://', 'any');
        $timeoutEverywhere = (new RecordingResponseFactory())->answer('https://', 'any', '', ['error' => 'Idle timeout reached']);

        foreach ([$refusedEverywhere, $timeoutEverywhere] as $upstream) {
            $this->upstream = $upstream;

            foreach ($this->isbnProviders() as $provider) {
                if ($provider instanceof DeferredIsbnCoverProviderInterface) {
                    $this->assertNull($provider->requestByIsbn(Isbn::fromString(self::ISBN))(), $provider::class);
                }
            }

            foreach ($this->contextProviders() as $provider) {
                if ($provider instanceof DeferredContextCoverProviderInterface) {
                    $this->assertSame([], $provider->requestAllByContext('Berserk', null, 3)(), $provider::class);
                }
            }
        }
    }

    private function composite(): CompositeMangaCoverApiClient
    {
        return new CompositeMangaCoverApiClient($this->isbnProviders(), $this->contextProviders(), new NullLogger());
    }

    /** @return list<MangaCoverProviderInterface> the ISBN cascade, in its production priority order */
    private function isbnProviders(): array
    {
        $httpClient = new MockHttpClient($this->upstream);
        $logger     = new NullLogger();

        return [
            new MangaDexMangaApiClient($httpClient, 'https://api.mangadex.org', $logger),
            new BnfCoversApiClient($httpClient, 'https://catalogue.bnf.fr', $logger),
            new OpenLibraryCoversApiClient($httpClient, 'https://covers.openlibrary.org', $logger),
            new GoogleBooksDynamicLinksApiClient($httpClient, 'https://books.google.com', $logger),
            new HardcoverCoversApiClient($httpClient, 'https://api.hardcover.app/v1/graphql', 'token', $logger),
        ];
    }

    /** @return list<MultiContextCoverProviderInterface> the title-search sources, in priority order */
    private function contextProviders(): array
    {
        $httpClient = new MockHttpClient($this->upstream);
        $logger     = new NullLogger();

        return [
            new MangaDexMangaApiClient($httpClient, 'https://api.mangadex.org', $logger),
            new GoogleBooksMangaApiClient($httpClient, '', $logger),
        ];
    }
}
