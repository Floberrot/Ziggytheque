<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\Marketplace;
use App\Manga\Domain\PriceKindEnum;
use App\Manga\Domain\PriceOfferDto;
use App\Manga\Domain\VolumePriceProviderInterface;
use App\Manga\Infrastructure\ExternalApi\Ebay\EbayOAuthTokenProvider;
use Psr\Log\LoggerInterface;
use Throwable;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class EbayBrowsePriceProvider implements VolumePriceProviderInterface
{
    private const string LOG_PREFIX = 'EBAY BROWSE : ';

    public function __construct(
        private HttpClientInterface $httpClient,
        private EbayOAuthTokenProvider $tokenProvider,
        private string $baseUrl,
        private string $campaignId,
        private LoggerInterface $logger,
    ) {
    }

    public function findOffers(Isbn $isbn, Marketplace $marketplace): array
    {
        $token = $this->tokenProvider->getToken();
        if ($token === null) {
            return [];
        }

        $this->logger->info(self::LOG_PREFIX . 'findOffers; BEGIN.', [
            'isbn'        => $isbn->value,
            'marketplace' => $marketplace->value,
        ]);

        try {
            return $this->doFindOffers($isbn, $marketplace, $token);
        } catch (Throwable $exception) {
            $this->logger->error(self::LOG_PREFIX . 'findOffers; ERROR.', [
                'isbn'  => $isbn->value,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /** @return list<PriceOfferDto> */
    private function doFindOffers(Isbn $isbn, Marketplace $marketplace, string $token): array
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

        if ($this->campaignId !== '') {
            $headers['X-EBAY-C-ENDUSERCTX'] = sprintf('affiliateCampaignId=%s', $this->campaignId);
        }

        $response = $this->httpClient->request('GET', $url, ['headers' => $headers]);

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
