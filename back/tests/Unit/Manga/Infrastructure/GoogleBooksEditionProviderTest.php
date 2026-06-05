<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Service\EditionLineExtractor;
use App\Manga\Domain\Service\EditionRelevanceFilter;
use App\Manga\Domain\Service\PublisherNormalizer;
use App\Manga\Infrastructure\ExternalApi\GoogleBooksEditionProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleBooksEditionProviderTest extends TestCase
{
    private const string API_KEY = 'test-api-key-abc123';

    private function makeProvider(
        MockHttpClient $httpClient,
        string $apiKey = self::API_KEY,
    ): GoogleBooksEditionProvider {
        return new GoogleBooksEditionProvider(
            $httpClient,
            $apiKey,
            new NullLogger(),
            new EditionLineExtractor(),
            new EditionRelevanceFilter(new PublisherNormalizer()),
        );
    }

    private function fixture(): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 3) . '/Fixtures/GoogleBooks/berserk-volumes.json',
        );
    }

    public function testFindEditionsReturnsDtosWithIsbnAndCover(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixture(), ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, 'fr');

        $this->assertNotEmpty($editions);

        $isbns   = array_filter(array_column(array_map(fn ($e) => $e->toArray(), $editions), 'isbnSample'));
        $covers  = array_filter(array_column(array_map(fn ($e) => $e->toArray(), $editions), 'coverUrl'));

        $this->assertNotEmpty($isbns);
        $this->assertNotEmpty($covers);
    }

    public function testFindEditionsWithEmptyApiKeyReturnsEmptyWithoutRequest(): void
    {
        $requestCount = 0;
        $httpClient   = new MockHttpClient(function () use (&$requestCount): MockResponse {
            $requestCount++;

            return new MockResponse('{}', ['http_code' => 200]);
        });

        $editions = $this->makeProvider($httpClient, '')->findEditions('Berserk', null, null);

        $this->assertSame([], $editions);
        $this->assertSame(0, $requestCount);
    }

    public function testFindEditionsReturnsEmptyOnNonOkResponse(): void
    {
        // Null language sweeps several locales; every call returns 429.
        $httpClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse('', ['http_code' => 429]),
        );

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, null);

        $this->assertSame([], $editions);
    }

    public function testStrictlyFiltersToRequestedLanguage(): void
    {
        // Google biases by langRestrict but still returns foreign editions; a French
        // search must drop the English one rather than mislabel its country.
        $json = (string) json_encode([
            'items' => [
                [
                    'id'         => 'fr1',
                    'volumeInfo' => [
                        'title'               => 'Berserk, Vol. 1',
                        'publisher'           => 'Glénat',
                        'language'            => 'fr',
                        'industryIdentifiers' => [['type' => 'ISBN_13', 'identifier' => '9782723425483']],
                    ],
                ],
                [
                    'id'         => 'en1',
                    'volumeInfo' => [
                        'title'               => 'Berserk, Vol. 1',
                        'publisher'           => 'Dark Horse',
                        'language'            => 'en',
                        'industryIdentifiers' => [['type' => 'ISBN_13', 'identifier' => '9781593070205']],
                    ],
                ],
            ],
        ]);

        $httpClient = new MockHttpClient([new MockResponse($json, ['http_code' => 200])]);

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, 'fr');

        $this->assertNotEmpty($editions);
        foreach ($editions as $edition) {
            $this->assertSame('fr', $edition->language);
        }
    }

    public function testNullLanguageSweepsLocalesAndDeduplicatesById(): void
    {
        $requestedLocales = [];
        $httpClient = new MockHttpClient(
            function (string $method, string $url) use (&$requestedLocales): MockResponse {
                preg_match('/langRestrict=([a-z]{2})/', $url, $matches);
                $requestedLocales[] = $matches[1] ?? '';

                return new MockResponse($this->fixture(), ['http_code' => 200]);
            },
        );

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, null);

        // Several markets are queried, not just French.
        $this->assertContains('fr', $requestedLocales);
        $this->assertContains('ja', $requestedLocales);
        $this->assertGreaterThan(1, count($requestedLocales));

        // The same two volume ids come back from every locale → deduplicated to two.
        $this->assertCount(2, $editions);
    }

    public function testFindEditionsSetsSourceAsGoogleBooks(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixture(), ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, 'fr');

        foreach ($editions as $edition) {
            $this->assertSame('google_books', $edition->source);
        }
    }
}
