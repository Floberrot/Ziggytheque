<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\EditionFormatEnum;
use App\Manga\Domain\Service\EditionLineExtractor;
use App\Manga\Domain\Service\EditionRelevanceFilter;
use App\Manga\Domain\Service\PublisherNormalizer;
use App\Manga\Infrastructure\ExternalApi\OpenLibraryEditionProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OpenLibraryEditionProviderTest extends TestCase
{
    private const string BASE_URL   = 'https://openlibrary.org';
    private const string USER_AGENT = 'Ziggytheque/1.0 (test@test.local)';

    private function makeProvider(MockHttpClient $httpClient): OpenLibraryEditionProvider
    {
        return new OpenLibraryEditionProvider(
            $httpClient,
            self::BASE_URL,
            self::USER_AGENT,
            new NullLogger(),
            new EditionLineExtractor(),
            new EditionRelevanceFilter(new PublisherNormalizer()),
        );
    }

    private function searchFixture(): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 3) . '/Fixtures/OpenLibrary/berserk-search.json',
        );
    }

    private function editionsFixture(): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 3) . '/Fixtures/OpenLibrary/berserk-editions.json',
        );
    }

    public function testFindEditionsReturnsBothEnAndFrEditions(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->searchFixture(), ['http_code' => 200]),
            new MockResponse($this->editionsFixture(), ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, null);

        $this->assertNotEmpty($editions);
        $languages = array_column(array_map(fn ($e) => $e->toArray(), $editions), 'language');
        $this->assertContains('en', $languages);
        $this->assertContains('fr', $languages);
    }

    public function testHardcoverMapsToRelie(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->searchFixture(), ['http_code' => 200]),
            new MockResponse($this->editionsFixture(), ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, null);

        $formats = array_map(fn ($e) => $e->format, $editions);
        $this->assertContains(EditionFormatEnum::Relie, $formats);
    }

    public function testEnLanguageMapsToCoverUrl(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->searchFixture(), ['http_code' => 200]),
            new MockResponse($this->editionsFixture(), ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, null);

        $enEditions = array_filter($editions, fn ($e) => $e->language === 'en' && $e->coverUrl !== null);
        $this->assertNotEmpty($enEditions);

        foreach ($enEditions as $edition) {
            $this->assertStringContainsString('covers.openlibrary.org', (string) $edition->coverUrl);
        }
    }

    public function testEnLanguageCountryIsUs(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->searchFixture(), ['http_code' => 200]),
            new MockResponse($this->editionsFixture(), ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, null);

        $enEditions = array_filter($editions, fn ($e) => $e->language === 'en');
        $this->assertNotEmpty($enEditions);

        foreach ($enEditions as $edition) {
            $this->assertSame('US', $edition->country);
        }
    }

    public function testUserAgentHeaderIsSent(): void
    {
        $sentHeaders = [];
        $httpClient  = new MockHttpClient(function (string $method, string $url, array $options) use (&$sentHeaders): MockResponse {
            $sentHeaders[] = $options['headers'] ?? [];

            if (str_contains($url, 'search.json')) {
                return new MockResponse($this->searchFixture(), ['http_code' => 200]);
            }

            return new MockResponse($this->editionsFixture(), ['http_code' => 200]);
        });

        $this->makeProvider($httpClient)->findEditions('Berserk', null, null);

        $this->assertNotEmpty($sentHeaders);
        $allHeaders = array_merge(...$sentHeaders);
        $this->assertContains('User-Agent: ' . self::USER_AGENT, $allHeaders);
    }

    public function testFiltersStrictlyToRequestedLanguage(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->searchFixture(), ['http_code' => 200]),
            new MockResponse($this->editionsFixture(), ['http_code' => 200]),
        ]);

        // The editions fixture holds English (Dark Horse) and French (Glénat) entries;
        // a French request must return only the French one.
        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, 'fr');

        $this->assertNotEmpty($editions);
        foreach ($editions as $edition) {
            $this->assertSame('fr', $edition->language);
        }
    }

    public function testReturnsEmptyWhenWorkNotFound(): void
    {
        $emptySearch = '{"numFound": 0, "start": 0, "docs": []}';

        $httpClient = new MockHttpClient([
            new MockResponse($emptySearch, ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('UnknownMangaTitle', null, null);

        $this->assertSame([], $editions);
    }

    public function testReturnsEmptyOnSearchError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 503]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, null);

        $this->assertSame([], $editions);
    }

    public function testMergesEditionsFromSeveralMatchingWorksAndSkipsUnrelatedOnes(): void
    {
        // Open Library splits one manga across several Work records (main run, deluxe
        // re-release…): every title-matching work contributes, unrelated docs do not.
        $searchJson = (string) json_encode([
            'numFound' => 3,
            'start'    => 0,
            'docs'     => [
                ['key' => '/works/OL1W', 'title' => 'Berserk', 'author_name' => ['Kentaro Miura']],
                ['key' => '/works/OL2W', 'title' => 'Berserk Deluxe', 'author_name' => ['Kentaro Miura']],
                ['key' => '/works/OL9W', 'title' => 'Cooking with Gordon', 'author_name' => ['Gordon Ramsay']],
            ],
        ]);

        $mainRunJson = (string) json_encode([
            'entries' => [
                [
                    'key'             => '/books/OL1001M',
                    'title'           => 'Berserk Vol. 1',
                    'publishers'      => ['Dark Horse Comics'],
                    'languages'       => [['key' => '/languages/eng']],
                    'isbn_13'         => ['9781593070205'],
                    'physical_format' => 'Paperback',
                ],
            ],
        ]);

        // OL2001M overlaps with a fresh key; OL1001M is repeated to prove deduplication.
        $deluxeJson = (string) json_encode([
            'entries' => [
                [
                    'key'             => '/books/OL1001M',
                    'title'           => 'Berserk Vol. 1',
                    'publishers'      => ['Dark Horse Comics'],
                    'languages'       => [['key' => '/languages/eng']],
                    'isbn_13'         => ['9781593070205'],
                    'physical_format' => 'Paperback',
                ],
                [
                    'key'             => '/books/OL2001M',
                    'title'           => 'Berserk Deluxe Volume 1',
                    'publishers'      => ['Dark Horse Comics'],
                    'languages'       => [['key' => '/languages/eng']],
                    'isbn_13'         => ['9781506711980'],
                    'physical_format' => 'Hardcover',
                ],
            ],
        ]);

        $requestedEditionUrls = [];
        $httpClient           = new MockHttpClient(
            function (string $method, string $url) use (
                &$requestedEditionUrls,
                $searchJson,
                $mainRunJson,
                $deluxeJson,
            ): MockResponse {
                if (str_contains($url, 'search.json')) {
                    return new MockResponse($searchJson, ['http_code' => 200]);
                }

                $requestedEditionUrls[] = $url;
                if (str_contains($url, '/works/OL1W/editions.json')) {
                    return new MockResponse($mainRunJson, ['http_code' => 200]);
                }
                if (str_contains($url, '/works/OL2W/editions.json')) {
                    return new MockResponse($deluxeJson, ['http_code' => 200]);
                }

                return new MockResponse('{}', ['http_code' => 404]);
            },
        );

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', 'Miura', null);

        $this->assertCount(2, $requestedEditionUrls);
        $this->assertStringContainsString('/works/OL1W/editions.json', $requestedEditionUrls[0]);
        $this->assertStringContainsString('/works/OL2W/editions.json', $requestedEditionUrls[1]);

        // 1 + 2 entries with one duplicate key → 2 distinct editions.
        $this->assertCount(2, $editions);
        $externalIds = array_column(array_map(fn ($edition) => $edition->toArray(), $editions), 'externalId');
        $this->assertSame(['/books/OL1001M', '/books/OL2001M'], $externalIds);
    }
}
