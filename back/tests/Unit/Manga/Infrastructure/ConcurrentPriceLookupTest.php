<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\Marketplace;
use App\Manga\Domain\PriceOfferDto;
use App\Manga\Infrastructure\ExternalApi\ChasseAuxLivresPriceProvider;
use App\Manga\Infrastructure\ExternalApi\CompositePriceProvider;
use App\Manga\Infrastructure\ExternalApi\DeferredPriceProviderInterface;
use App\Manga\Infrastructure\ExternalApi\Ebay\EbayOAuthTokenProvider;
use App\Manga\Infrastructure\ExternalApi\EbayBrowsePriceProvider;
use App\Manga\Infrastructure\ExternalApi\GoogleBooksPriceProvider;
use App\Tests\Doubles\Http\RecordingResponseFactory;
use Closure;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * The price lookup asks eBay, Chasse aux livres and Google Books at the same time: every
 * request is sent before any answer is read, the offers come back in the sources' order,
 * and one source failing or timing out never costs the others theirs.
 */
final class ConcurrentPriceLookupTest extends TestCase
{
    private const string ISBN = '9782723425483';

    private RecordingResponseFactory $upstream;
    private ArrayAdapter $tokenCache;

    protected function setUp(): void
    {
        $this->upstream = (new RecordingResponseFactory())
            ->answer('/identity/v1/oauth2/token', 'ebay-oauth', $this->fixture('Ebay/oauth-token.json'))
            ->answer('/buy/browse/', 'ebay', $this->fixture('Ebay/browse-search.json'))
            ->answer('chasse-aux-livres.fr/prix/', 'chasse-aux-livres', $this->fixture('ChasseAuxLivres/prix-berserk.html'))
            ->answer('googleapis.com/books/', 'google-books', $this->fixture('GoogleBooks/saleinfo-fr.json'));
        $this->tokenCache = new ArrayAdapter();
    }

    public function testSendsEveryRequestBeforeReadingAnyAnswer(): void
    {
        $this->cacheEbayToken();

        $this->findOffers();

        $this->assertSame(['ebay', 'chasse-aux-livres', 'google-books'], $this->upstream->sentSources());
        $this->assertTrue($this->upstream->sentBeforeAnyRead('ebay', 'chasse-aux-livres', 'google-books'));
        $this->assertSame(['ebay', 'chasse-aux-livres', 'google-books'], $this->upstream->readSources());
    }

    public function testKeepsTheOffersInTheSourcesOrder(): void
    {
        $this->cacheEbayToken();

        $sources = array_map(static fn (PriceOfferDto $offer): string => $offer->source, $this->findOffers());

        $this->assertSame(
            ['ebay', 'ebay', 'chasse_aux_livres', 'chasse_aux_livres', 'chasse_aux_livres', 'chasse_aux_livres',
                'chasse_aux_livres', 'google_books'],
            $sources,
        );
    }

    public function testFindsTheSameOffersAsAskingTheSourcesOneByOne(): void
    {
        $this->cacheEbayToken();
        $concurrentOffers = $this->findOffers();

        $sequentialOffers = [];
        foreach ($this->providers() as $provider) {
            foreach ($provider->findOffers(Isbn::fromString(self::ISBN), Marketplace::Fr) as $offer) {
                $sequentialOffers[] = $offer;
            }
        }

        $this->assertEquals($sequentialOffers, $concurrentOffers);
    }

    /** No eBay token yet: its OAuth call runs alongside the other sources, its search follows. */
    public function testAColdEbayTokenIsFetchedAlongsideTheOtherSources(): void
    {
        $offers = $this->findOffers();

        $this->assertTrue($this->upstream->sentBeforeAnyRead('ebay-oauth', 'chasse-aux-livres', 'google-books'));
        $this->assertSame(
            ['ebay-oauth', 'chasse-aux-livres', 'google-books', 'ebay'],
            $this->upstream->sentSources(),
        );
        $this->assertSame('ebay', $offers[0]->source);
        $this->assertNotNull($this->ebayTokenProvider()->cachedToken());
    }

    public function testASourceThatTimesOutOnlyLosesItsOwnOffers(): void
    {
        $this->cacheEbayToken();
        $this->upstream->answer('chasse-aux-livres.fr/prix/', 'chasse-aux-livres', '', ['error' => 'Idle timeout reached']);

        $sources = array_values(array_unique(array_map(
            static fn (PriceOfferDto $offer): string => $offer->source,
            $this->findOffers(),
        )));

        $this->assertSame(['ebay', 'google_books'], $sources);
    }

    public function testASourceAnsweringAnErrorOnlyLosesItsOwnOffers(): void
    {
        $this->cacheEbayToken();
        $this->upstream->answer('/buy/browse/', 'ebay', '{}', ['http_code' => 503]);

        $sources = array_values(array_unique(array_map(
            static fn (PriceOfferDto $offer): string => $offer->source,
            $this->findOffers(),
        )));

        $this->assertSame(['chasse_aux_livres', 'google_books'], $sources);
    }

    public function testASourceWhoseRequestCannotBeSentOnlyLosesItsOwnOffers(): void
    {
        $this->cacheEbayToken();
        $this->upstream->refuse('googleapis.com/books/', 'google-books');

        $sources = array_values(array_unique(array_map(
            static fn (PriceOfferDto $offer): string => $offer->source,
            $this->findOffers(),
        )));

        $this->assertSame(['ebay', 'chasse_aux_livres'], $sources);
    }

    public function testASourceThatThrowsWhileStartingIsSkippedInItsTurn(): void
    {
        $failingSource = new class implements DeferredPriceProviderInterface {
            public function requestOffers(Isbn $isbn, Marketplace $marketplace): Closure
            {
                throw new RuntimeException('cannot start');
            }

            public function findOffers(Isbn $isbn, Marketplace $marketplace): array
            {
                return $this->requestOffers($isbn, $marketplace)();
            }
        };
        $this->cacheEbayToken();

        $composite = new CompositePriceProvider([$failingSource, ...$this->providers()], new NullLogger());
        $offers    = $composite->findOffers(Isbn::fromString(self::ISBN), Marketplace::Fr);

        $this->assertSame('ebay', $offers[0]->source);
        $this->assertTrue($this->upstream->sentBeforeAnyRead('ebay', 'chasse-aux-livres', 'google-books'));
    }

    public function testEachSourceStartsWithoutWaitingForItsAnswer(): void
    {
        $this->cacheEbayToken();

        $pendingOffers = [];
        foreach ($this->providers() as $provider) {
            $pendingOffers[] = $provider->requestOffers(Isbn::fromString(self::ISBN), Marketplace::Fr);
        }

        $this->assertSame(['ebay', 'chasse-aux-livres', 'google-books'], $this->upstream->sentSources());
        $this->assertSame([], $this->upstream->readSources());

        foreach ($pendingOffers as $readOffers) {
            $this->assertNotSame([], $readOffers());
        }
        $this->assertSame(['ebay', 'chasse-aux-livres', 'google-books'], $this->upstream->readSources());
    }

    /** Each source swallows its own failure — sending or reading — and finds nothing. */
    public function testEverySourceFindsNothingWhenItsCallCannotBeSentOrTimesOut(): void
    {
        $refusedEverywhere = (new RecordingResponseFactory())->refuse('https://', 'any');
        $timeoutEverywhere = (new RecordingResponseFactory())->answer('https://', 'any', '', ['error' => 'Idle timeout reached']);

        foreach ([$refusedEverywhere, $timeoutEverywhere] as $upstream) {
            $this->upstream = $upstream;

            // Cached token (search sent at once) and cold token (OAuth call first).
            foreach ([true, false] as $tokenCached) {
                $this->tokenCache->clear();
                if ($tokenCached) {
                    $this->cacheEbayToken();
                }

                foreach ($this->providers() as $provider) {
                    $offers = $provider->requestOffers(Isbn::fromString(self::ISBN), Marketplace::Fr)();
                    $this->assertSame([], $offers, $provider::class);
                }
            }
        }
    }

    /** @return list<PriceOfferDto> */
    private function findOffers(): array
    {
        return (new CompositePriceProvider($this->providers(), new NullLogger()))
            ->findOffers(Isbn::fromString(self::ISBN), Marketplace::Fr);
    }

    /** @return list<DeferredPriceProviderInterface> in their production priority order */
    private function providers(): array
    {
        $httpClient = new MockHttpClient($this->upstream);
        $logger     = new NullLogger();

        return [
            new EbayBrowsePriceProvider(
                $httpClient,
                $this->ebayTokenProvider($httpClient),
                'https://api.ebay.com',
                '',
                $logger,
            ),
            new ChasseAuxLivresPriceProvider($httpClient, 'https://www.chasse-aux-livres.fr', $logger),
            new GoogleBooksPriceProvider($httpClient, 'test-key', $logger),
        ];
    }

    private function ebayTokenProvider(?MockHttpClient $httpClient = null): EbayOAuthTokenProvider
    {
        return new EbayOAuthTokenProvider(
            $httpClient ?? new MockHttpClient($this->upstream),
            'https://api.ebay.com/identity/v1/oauth2/token',
            'client-id',
            'client-secret',
            $this->tokenCache,
            new NullLogger(),
        );
    }

    private function cacheEbayToken(): void
    {
        $item = $this->tokenCache->getItem('ebay.oauth.token');
        $item->set('cached-token');
        $this->tokenCache->save($item);
    }

    private function fixture(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 3) . '/Fixtures/' . $path);
    }
}
