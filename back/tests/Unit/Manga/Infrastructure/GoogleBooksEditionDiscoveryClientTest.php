<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Country;
use App\Manga\Domain\Service\EditionGrouper;
use App\Manga\Infrastructure\ExternalApi\GoogleBooksEditionDiscoveryClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleBooksEditionDiscoveryClientTest extends TestCase
{
    private const string API_KEY = 'test-api-key';

    private function makeClient(MockHttpClient $httpClient): GoogleBooksEditionDiscoveryClient
    {
        return new GoogleBooksEditionDiscoveryClient(
            $httpClient,
            self::API_KEY,
            new EditionGrouper(),
            new NullLogger(),
        );
    }

    private function makeVolumeItem(string $publisher, int $volumeNumber, string $title = 'Berserk'): array
    {
        return [
            'id' => 'vol-' . $volumeNumber,
            'volumeInfo' => [
                'title'         => $title . ' T0' . $volumeNumber,
                'publisher'     => $publisher,
                'publishedDate' => '2019-0' . $volumeNumber . '-01',
                'language'      => 'fr',
                'imageLinks'    => [
                    'thumbnail' => 'https://books.google.com/cover' . $volumeNumber . '.jpg',
                ],
                'industryIdentifiers' => [
                    ['type' => 'ISBN_13', 'identifier' => '978234402081' . $volumeNumber],
                ],
                'seriesInfo' => [
                    'bookDisplayNumber' => (string) $volumeNumber,
                ],
            ],
        ];
    }

    public function testDiscoverEditionsReturnsTwoEditionsForTwoPublishers(): void
    {
        $glennatItems = [
            $this->makeVolumeItem('Glénat', 1),
            $this->makeVolumeItem('Glénat', 2),
            $this->makeVolumeItem('Glénat', 3),
        ];
        $paniniItems = [
            $this->makeVolumeItem('Panini', 1, 'Berserk Panini'),
        ];

        $allItems = array_merge($glennatItems, $paniniItems);

        $httpClient = new MockHttpClient([
            new MockResponse((string) json_encode(['items' => $allItems])),
            new MockResponse((string) json_encode([])),
        ]);

        $client = $this->makeClient($httpClient);
        $editions = $client->discoverEditions('Berserk', Country::France);

        $this->assertCount(2, $editions);
        $publishers = array_map(fn ($edition) => $edition->publisher, $editions);
        $this->assertContains('Glénat', $publishers);
        $this->assertContains('Panini', $publishers);
        $this->assertSame('google_books', $editions[0]->source);
    }

    public function testDiscoverEditionsFiltersOutMismatchedLanguageItems(): void
    {
        $frenchItem = $this->makeVolumeItem('Glénat', 1);
        $englishItem = [
            'id' => 'vol-en',
            'volumeInfo' => [
                'title'     => 'Berserk Vol.1',
                'publisher' => 'Dark Horse',
                'language'  => 'en',
                'industryIdentifiers' => [
                    ['type' => 'ISBN_13', 'identifier' => '9781506711980'],
                ],
            ],
        ];

        $httpClient = new MockHttpClient([
            new MockResponse((string) json_encode(['items' => [$frenchItem, $englishItem]])),
            new MockResponse((string) json_encode([])),
        ]);

        $client = $this->makeClient($httpClient);
        $editions = $client->discoverEditions('Berserk', Country::France);

        $this->assertCount(1, $editions);
        $this->assertSame('Glénat', $editions[0]->publisher);
    }

    public function testDiscoverEditionsReturnsEmptyOnHttpError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Service Unavailable', ['http_code' => 503]),
            new MockResponse('Service Unavailable', ['http_code' => 503]),
        ]);

        $client = $this->makeClient($httpClient);
        $editions = $client->discoverEditions('Berserk', Country::France);

        $this->assertSame([], $editions);
    }

    public function testDiscoverEditionsIgnoresItemsWithoutPublisher(): void
    {
        $itemWithoutPublisher = [
            'id' => 'vol-no-pub',
            'volumeInfo' => [
                'title'    => 'Berserk T01',
                'language' => 'fr',
                'imageLinks' => ['thumbnail' => 'https://cover.jpg'],
            ],
        ];

        $httpClient = new MockHttpClient([
            new MockResponse((string) json_encode(['items' => [$itemWithoutPublisher]])),
            new MockResponse((string) json_encode([])),
        ]);

        $client = $this->makeClient($httpClient);
        $editions = $client->discoverEditions('Berserk', Country::France);

        $this->assertSame([], $editions);
    }
}
