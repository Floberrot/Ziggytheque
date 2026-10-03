<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\Marketplace;
use App\Manga\Domain\PriceKindEnum;
use App\Manga\Domain\PriceOfferDto;
use App\Manga\Infrastructure\ExternalApi\Ebay\EbayOAuthTokenProvider;
use Closure;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

final readonly class EbayBrowsePriceProvider implements DeferredPriceProviderInterface
{
    private const string LOG_PREFIX = 'EBAY BROWSE : ';

    public function __construct(
        private HttpClientInterface $httpClient,
        private EbayOAuthTokenProvider $tokenProvider,
        private string $baseUrl,
        private ?string $campaignId,
        private LoggerInterface $logger,
    ) {
    }

    public function findOffers(Isbn $isbn, Marketplace $marketplace): array
    {
        return $this->requestOffers($isbn, $marketplace)();
    }

    public function requestOffers(Isbn $isbn, Marketplace $marketplace): Closure
    {
        $cachedToken = $this->tokenProvider->cachedToken();

        if ($cachedToken !== null) {
            // The usual case: the search starts now, alongside the other price sources.
            $pendingSearch = $this->startSearch($isbn, $marketplace, $cachedToken);

            return fn (): array => $pendingSearch !== null ? $this->readSearch($isbn, $pendingSearch) : [];
        }

        // No token yet: its OAuth call runs alongside the other sources, the search follows it.
        $readToken = $this->tokenProvider->requestToken();

        return function () use ($isbn, $marketplace, $readToken): array {
            $token = $readToken();
            if ($token === null) {
                return [];
            }

            $pendingSearch = $this->startSearch($isbn, $marketplace, $token);

            return $pendingSearch !== null ? $this->readSearch($isbn, $pendingSearch) : [];
        };
    }

    /** Sends the search without waiting for it; null when it cannot even be sent. */
    private function startSearch(Isbn $isbn, Marketplace $marketplace, string $token): ?ResponseInterface
    {
        $this->logger->info(self::LOG_PREFIX . 'findOffers; BEGIN.', [
            'isbn'        => $isbn->value,
            'marketplace' => $marketplace->value,
        ]);

        try {
            return $this->sendSearch($isbn, $marketplace, $token);
        } catch (Throwable $exception) {
            $this->logError($isbn, $exception);

            return null;
        }
    }

    /** @return list<PriceOfferDto> */
    private function readSearch(Isbn $isbn, ResponseInterface $response): array
    {
        try {
            return $this->readOffers($response);
        } catch (Throwable $exception) {
            $this->logError($isbn, $exception);

            return [];
        }
    }

    private function logError(Isbn $isbn, Throwable $exception): void
    {
        $this->logger->error(self::LOG_PREFIX . 'findOffers; ERROR.', [
            'isbn'  => $isbn->value,
            'error' => $exception->getMessage(),
        ]);
    }

    private function sendSearch(Isbn $isbn, Marketplace $marketplace, string $token): ResponseInterface
    {
        $url = sprintf(
            '%s/buy/browse/v1/item_summary/search?gtin=%s&limit=3',
            $this->baseUrl,
            $isbn->value,
        );

        $headers = [
            'Authorization'            => sprintf('Bearer %s', $token),
            'X-EBAY-C-MARKETPLACE-ID'  => $marketplace->ebayId(),
        ];

        if ($this->campaignId !== null && $this->campaignId !== '') {
            $headers['X-EBAY-C-ENDUSERCTX'] = sprintf('affiliateCampaignId=%s', $this->campaignId);
        }

        return $this->httpClient->request('GET', $url, ['headers' => $headers]);
    }

    /** @return list<PriceOfferDto> */
    private function readOffers(ResponseInterface $response): array
    {
        if ($response->getStatusCode() !== 200) {
            $this->logger->info(self::LOG_PREFIX . 'findOffers; NOT 200.', [
                'status' => $response->getStatusCode(),
            ]);

            return [];
        }

        /** @var array{itemSummaries?: list<array<string, mixed>>} $data */
        $data  = json_decode($response->getContent(), true);
        $items = $data['itemSummaries'] ?? [];

        $offers = [];
        foreach ($items as $item) {
            $offer = $this->buildOffer($item);
            if ($offer !== null) {
                $offers[] = $offer;
            }
        }

        return $offers;
    }

    /** @param array<string, mixed> $item */
    private function buildOffer(array $item): ?PriceOfferDto
    {
        $priceData = $item['price'] ?? null;
        if ($priceData === null) {
            return null;
        }

        $amount   = (float) ($priceData['value'] ?? 0.0);
        $currency = (string) ($priceData['currency'] ?? 'EUR');

        $url      = (string) ($item['itemAffiliateWebUrl'] ?? $item['itemWebUrl'] ?? '');
        $url      = $url !== '' ? $url : null;
        $imageUrl = ($item['image']['imageUrl'] ?? null);
        $imageUrl = $imageUrl !== null ? (string) $imageUrl : null;

        return new PriceOfferDto(
            kind:         PriceKindEnum::MerchantLive,
            merchant:     'eBay',
            merchantLogo: 'ebay',
            amount:       $amount,
            currency:     $currency,
            url:          $url,
            imageUrl:     $imageUrl,
            source:       'ebay',
        );
    }
}
