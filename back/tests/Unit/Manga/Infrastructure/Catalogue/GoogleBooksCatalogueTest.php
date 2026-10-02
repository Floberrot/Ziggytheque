<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure\Catalogue;

use App\Manga\Domain\Exception\CatalogueUnavailableException;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\Service\CatalogueTitleParser;
use App\Manga\Domain\Service\EditionRelevanceFilter;
use App\Manga\Domain\Service\PublisherNormalizer;
use App\Manga\Infrastructure\Catalogue\GoogleBooksCatalogue;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleBooksCatalogueTest extends TestCase
{
    /** @var list<string> */
    private array $requestedUrls = [];

    private function makeCatalogue(MockResponse|TransportException $response, string $apiKey = 'real-key'): GoogleBooksCatalogue
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) use ($response): MockResponse {
            $this->requestedUrls[] = urldecode($url);
            if ($response instanceof TransportException) {
                throw $response;
            }

            return $response;
        });

        return new GoogleBooksCatalogue(
            $httpClient,
            $apiKey,
            new NullLogger(),
            new CatalogueTitleParser(),
            new EditionRelevanceFilter(new PublisherNormalizer()),
        );
    }

    private function volumes(): MockResponse
    {
        return new MockResponse((string) json_encode(['items' => [
            ['volumeInfo' => [
                'title'               => 'Berserk',
                'subtitle'            => 'Tome 3',
                'publisher'           => 'Glénat',
                'authors'             => ['Kentaro Miura'],
                'language'            => 'fr',
                'industryIdentifiers' => [
                    ['type' => 'ISBN_10', 'identifier' => '2723425485'],
                    ['type' => 'ISBN_13', 'identifier' => '9782723428095'],
                ],
                'imageLinks'          => ['thumbnail' => 'http://books.google.com/books/content?id=abc'],
            ]],
            ['volumeInfo' => ['title' => 'Berserk', 'language' => 'en']],
            ['volumeInfo' => ['title' => '', 'language' => 'fr']],
            ['volumeInfo' => ['title' => 'Berserk calendrier 2026', 'language' => 'fr']],
            ['volumeInfo' => ['title' => 'Berserk', 'subtitle' => 'Tome 4', 'language' => 'fr']],
            ['volumeInfo' => [
                'title'               => 'Berserk Musou',
                'language'            => 'fr',
                'categories'          => ['Games & Activities'],
                'industryIdentifiers' => [['type' => 'ISBN_13', 'identifier' => '9782344061008']],
            ]],
            ['volumeInfo' => [
                'title'               => 'Berserk',
                'subtitle'            => 'Tome 1',
                'language'            => 'fr',
                'categories'          => ['Comics & Graphic Novels'],
                'industryIdentifiers' => [['type' => 'ISBN_10', 'identifier' => '2723425487']],
            ]],
        ]]));
    }

    public function testSearchByTitleParsesFrenchVolumes(): void
    {
        // Dropped: English, untitled, calendar, no ISBN, a game.
        $records = $this->makeCatalogue($this->volumes())->searchByTitle('Berserk');

        $this->assertCount(2, $records);
        $this->assertSame('9782723425483', $records[1]->isbn?->value, 'An ISBN-10 is converted when no ISBN-13 is given.');
        $record = $records[0];
        $this->assertSame('Berserk', $record->workTitle);
        $this->assertSame(3, $record->volumeNumber);
        $this->assertSame('Glénat', $record->publisher);
        $this->assertSame('Kentaro Miura', $record->author);
        $this->assertSame('9782723428095', $record->isbn?->value);
        $this->assertSame('https://books.google.com/books/content?id=abc', $record->coverUrl);
        $this->assertSame('google_books', $record->source);

        $this->assertStringContainsString('q=intitle:Berserk', $this->requestedUrls[0]);
        $this->assertStringContainsString('langRestrict=fr', $this->requestedUrls[0]);
    }

    public function testAuthorAndIsbnSearchesUseTheirOperators(): void
    {
        $catalogue = $this->makeCatalogue($this->volumes());

        $catalogue->searchByAuthor('Miura');
        $catalogue->findByIsbn(Isbn::fromString('9782723428095'));

        $this->assertStringContainsString('q=inauthor:Miura', $this->requestedUrls[0]);
        $this->assertStringContainsString('q=isbn:9782723428095', $this->requestedUrls[1]);
    }

    public function testPlaceholderKeyNeverCallsGoogle(): void
    {
        $this->assertSame([], $this->makeCatalogue($this->volumes(), 'change_me')->searchByTitle('Berserk'));
        $this->assertSame([], $this->requestedUrls);
    }

    /** A quota error or a network failure is an outage, not "no book". */
    public function testAQuotaErrorIsAnOutage(): void
    {
        $this->expectException(CatalogueUnavailableException::class);

        $this->makeCatalogue(new MockResponse('', ['http_code' => 429]))->searchByTitle('Berserk');
    }

    public function testATransportErrorIsAnOutage(): void
    {
        $this->expectException(CatalogueUnavailableException::class);

        $this->makeCatalogue(new TransportException('down'))->searchByTitle('Berserk');
    }
}
