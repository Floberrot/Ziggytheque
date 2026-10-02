<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure\Catalogue;

use App\Manga\Domain\Exception\CatalogueUnavailableException;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\Service\CatalogueTitleParser;
use App\Manga\Domain\Service\EditionRelevanceFilter;
use App\Manga\Domain\Service\PublisherNormalizer;
use App\Manga\Infrastructure\Catalogue\BnfCatalogue;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BnfCatalogueTest extends TestCase
{
    private const string BASE_URL = 'https://catalogue.bnf.fr';

    /** @var list<string> */
    private array $requestedUrls = [];

    private function makeCatalogue(MockResponse|TransportException $response): BnfCatalogue
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) use ($response): MockResponse {
            $this->requestedUrls[] = urldecode($url);
            if ($response instanceof TransportException) {
                throw $response;
            }

            return $response;
        });

        return new BnfCatalogue(
            $httpClient,
            self::BASE_URL,
            new NullLogger(),
            new CatalogueTitleParser(),
            new EditionRelevanceFilter(new PublisherNormalizer()),
        );
    }

    private function fixture(): MockResponse
    {
        return new MockResponse((string) file_get_contents(__DIR__ . '/../../../../Fixtures/Bnf/catalogue-berserk-dublincore.xml'));
    }

    public function testSearchByTitleKeepsFrenchPrintedBooksWithAnIsbnOnly(): void
    {
        // Dropped: a video game, a book without ISBN, a film, a German tome, coloriages, a record without title.
        $records = $this->makeCatalogue($this->fixture())->searchByTitle('Berserk');

        $this->assertCount(3, $records);

        $standard = $records[0];
        $this->assertSame('Berserk', $standard->workTitle);
        $this->assertNull($standard->headQualifier);
        $this->assertSame(1, $standard->volumeNumber);
        $this->assertSame('Glénat (Grenoble)', $standard->publisher);
        $this->assertSame('Kentarō Miura', $standard->author);
        $this->assertSame('9782723425483', $standard->isbn?->value);
        $this->assertSame(
            self::BASE_URL . '/couverture?appName=NE&idArk=ark:/12148/cb37148339x&couverture=1',
            $standard->coverUrl,
        );
        $this->assertSame('bnf', $standard->source);

        $prestige = $records[1];
        $this->assertSame('Prestige', $prestige->headQualifier);
        $this->assertSame(2, $prestige->volumeNumber);
        $this->assertSame("L'élu", $prestige->trailingQualifier);
        $this->assertSame('9782344036082', $prestige->isbn?->value);

        $statedEdition = $records[2];
        $this->assertSame('Berserk', $statedEdition->workTitle);
        $this->assertSame('Édition prestige', $statedEdition->headQualifier);
        $this->assertSame(5, $statedEdition->volumeNumber);
        $this->assertSame('9782344050002', $statedEdition->isbn?->value);

        $this->assertStringContainsString('query=bib.title all "Berserk"', $this->requestedUrls[0]);
        $this->assertStringContainsString('recordSchema=dublincore', $this->requestedUrls[0]);
    }

    public function testSearchByAuthorUsesTheAuthorIndex(): void
    {
        $records = $this->makeCatalogue($this->fixture())->searchByAuthor('Miura');

        $this->assertCount(3, $records);
        $this->assertStringContainsString('query=bib.author all "Miura"', $this->requestedUrls[0]);
    }

    public function testFindByIsbnUsesTheIsbnIndex(): void
    {
        $this->makeCatalogue($this->fixture())->findByIsbn(Isbn::fromString('9782723425483'));

        $this->assertStringContainsString('query=bib.isbn all "9782723425483"', $this->requestedUrls[0]);
    }

    public function testDoubleQuotesAreStrippedFromTheQuery(): void
    {
        $this->makeCatalogue($this->fixture())->searchByTitle('Ber"serk');

        $this->assertStringContainsString('query=bib.title all "Berserk"', $this->requestedUrls[0]);
    }

    /** A BnF outage must not read as "no French edition". */
    public function testAServerErrorIsAnOutage(): void
    {
        $this->expectException(CatalogueUnavailableException::class);

        $this->makeCatalogue(new MockResponse('', ['http_code' => 503]))->searchByTitle('Berserk');
    }

    public function testATimeoutIsAnOutage(): void
    {
        $this->expectException(CatalogueUnavailableException::class);

        $this->makeCatalogue(new TransportException('timeout'))->searchByTitle('Berserk');
    }

    public function testAnUnreadableAnswerIsAnOutage(): void
    {
        $this->expectException(CatalogueUnavailableException::class);

        $this->makeCatalogue(new MockResponse('<html>maintenance'))->searchByTitle('Berserk');
    }

    public function testEmptyBodyReturnsNoRecord(): void
    {
        $this->assertSame([], $this->makeCatalogue(new MockResponse(''))->searchByTitle('Berserk'));
    }
}
