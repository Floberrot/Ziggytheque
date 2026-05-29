<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Country;
use App\Manga\Infrastructure\ExternalApi\OpenLibraryEditionDiscoveryClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OpenLibraryEditionDiscoveryClientTest extends TestCase
{
    private function makeClient(MockHttpClient $httpClient): OpenLibraryEditionDiscoveryClient
    {
        return new OpenLibraryEditionDiscoveryClient(
            $httpClient,
            'https://openlibrary.org',
            new NullLogger(),
        );
    }

    public function testDiscoverEditionsFiltersByLanguage(): void
    {
        $docs = [
            [
                'title'               => 'Berserk T01',
                'publisher'           => ['Glénat'],
                'first_publish_year'  => 2019,
                'isbn'                => ['9782344020814'],
                'language'            => ['fre'],
            ],
            [
                'title'               => 'Berserk Vol.1',
                'publisher'           => ['Dark Horse'],
                'first_publish_year'  => 2018,
                'isbn'                => ['9781506711980'],
                'language'            => ['eng'],
            ],
        ];

        $httpClient = new MockHttpClient([
            new MockResponse((string) json_encode(['docs' => $docs])),
        ]);

        $client = $this->makeClient($httpClient);
        $editions = $client->discoverEditions('Berserk', Country::France);

        $this->assertCount(1, $editions);
        $this->assertSame('Glénat', $editions[0]->publisher);
        $this->assertSame('open_library', $editions[0]->source);
    }

    public function testDiscoverEditionsReturnsEmptyOnHttpError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Error', ['http_code' => 500]),
        ]);

        $client = $this->makeClient($httpClient);
        $editions = $client->discoverEditions('Berserk', Country::France);

        $this->assertSame([], $editions);
    }

    public function testDiscoverEditionsDeduplicatesPublishers(): void
    {
        $docs = [
            [
                'title'              => 'Berserk T01',
                'publisher'          => ['Glénat'],
                'first_publish_year' => 2019,
                'isbn'               => ['9782344020814'],
                'language'           => ['fre'],
            ],
            [
                'title'              => 'Berserk T02',
                'publisher'          => ['Glénat'],
                'first_publish_year' => 2019,
                'isbn'               => ['9782344020813'],
                'language'           => ['fre'],
            ],
        ];

        $httpClient = new MockHttpClient([
            new MockResponse((string) json_encode(['docs' => $docs])),
        ]);

        $client = $this->makeClient($httpClient);
        $editions = $client->discoverEditions('Berserk', Country::France);

        $this->assertCount(1, $editions);
    }
}
