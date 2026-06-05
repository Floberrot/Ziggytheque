<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\Marketplace;
use App\Manga\Domain\PriceKindEnum;
use App\Manga\Domain\PriceOfferDto;
use App\Manga\Domain\VolumePriceProviderInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GoogleBooksPriceProvider implements VolumePriceProviderInterface
{
    private const string BASE_URL   = 'https://www.googleapis.com/books/v1';
    private const string LOG_PREFIX = 'GOOGLE BOOKS PRICES : ';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private LoggerInterface $logger,
    ) {
    }

    public function findOffers(Isbn $isbn, Marketplace $marketplace): array
    {
        if ($this->apiKey === '') {
            return [];
        }

        $this->logger->info(self::LOG_PREFIX . 'findOffers; BEGIN.', [
            'isbn'        => $isbn->value,
            'marketplace' => $marketplace->value,
        ]);

        try {
            return $this->doFindOffers($isbn, $marketplace);
        } catch (Throwable $exception) {
            $this->logger->error(self::LOG_PREFIX . 'findOffers; ERROR.', [
                'isbn'  => $isbn->value,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /** @return list<PriceOfferDto> */
    private function doFindOffers(Isbn $isbn, Marketplace $marketplace): array
    {
        $country = match ($marketplace) {
            Marketplace::Us => 'US',
            default         => 'FR',
        };

        $url = sprintf(
            '%s/volumes?q=isbn:%s&country=%s&key=%s',
            self::BASE_URL,
            $isbn->value,
            $country,
            $this->apiKey,
        );

        $response = $this->httpClient->request('GET', $url);
        if ($response->getStatusCode() !== 200) {
            return [];
        }

        /** @var array{items?: list<array<string, mixed>>} $data */
        $data  = json_decode($response->getContent(), true);
        $items = $data['items'] ?? [];

        if ($items === []) {
            return [];
        }

        $saleInfo = $items[0]['saleInfo'] ?? [];

        if (($saleInfo['saleability'] ?? '') !== 'FOR_SALE') {
            return [];
        }

        $priceData = $saleInfo['retailPrice'] ?? $saleInfo['listPrice'] ?? null;
        if ($priceData === null) {
            return [];
        }

        $amount   = (float) ($priceData['amount'] ?? 0.0);
        $currency = (string) ($priceData['currencyCode'] ?? $marketplace->currencyCode());
        $buyLink  = ($saleInfo['buyLink'] ?? null);
        $buyLink  = $buyLink !== null ? (string) $buyLink : null;

        return [new PriceOfferDto(
            kind:         PriceKindEnum::PublisherReference,
            merchant:     'Google Play',
            merchantLogo: 'google_play',
            amount:       $amount,
            currency:     $currency,
            url:          $buyLink,
            imageUrl:     null,
            source:       'google_books',
        )];
    }
}
