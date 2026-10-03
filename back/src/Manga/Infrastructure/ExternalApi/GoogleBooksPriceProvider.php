<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\Marketplace;
use App\Manga\Domain\PriceKindEnum;
use App\Manga\Domain\PriceOfferDto;
use Closure;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

final readonly class GoogleBooksPriceProvider implements DeferredPriceProviderInterface
{
    private const string BASE_URL   = 'https://www.googleapis.com/books/v1';
    private const string LOG_PREFIX = 'GOOGLE BOOKS PRICES : ';

    /**
     * Sentinel values the env-sync tooling (and back/.env defaults) leave in place of a
     * real key — sending them yields silent 403s, so treat them as "no key configured".
     * Same pattern as {@see GoogleBooksCatalogue}.
     *
     * @var list<string>
     */
    private const array PLACEHOLDER_API_KEYS = ['change_me', 'changeme'];

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private LoggerInterface $logger,
    ) {
    }

    public function findOffers(Isbn $isbn, Marketplace $marketplace): array
    {
        return $this->requestOffers($isbn, $marketplace)();
    }

    public function requestOffers(Isbn $isbn, Marketplace $marketplace): Closure
    {
        if (!$this->isApiKeyConfigured()) {
            return static fn (): array => [];
        }

        $this->logger->info(self::LOG_PREFIX . 'findOffers; BEGIN.', [
            'isbn'        => $isbn->value,
            'marketplace' => $marketplace->value,
        ]);

        try {
            $response = $this->sendRequest($isbn, $marketplace);
        } catch (Throwable $exception) {
            $this->logError($isbn, $exception);

            return static fn (): array => [];
        }

        return function () use ($isbn, $marketplace, $response): array {
            try {
                return $this->readOffers($response, $marketplace);
            } catch (Throwable $exception) {
                $this->logError($isbn, $exception);

                return [];
            }
        };
    }

    private function logError(Isbn $isbn, Throwable $exception): void
    {
        $this->logger->error(self::LOG_PREFIX . 'findOffers; ERROR.', [
            'isbn'  => $isbn->value,
            'error' => $exception->getMessage(),
        ]);
    }

    private function isApiKeyConfigured(): bool
    {
        return $this->apiKey !== ''
            && !in_array(strtolower($this->apiKey), self::PLACEHOLDER_API_KEYS, true);
    }

    private function sendRequest(Isbn $isbn, Marketplace $marketplace): ResponseInterface
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

        return $this->httpClient->request('GET', $url);
    }

    /** @return list<PriceOfferDto> */
    private function readOffers(ResponseInterface $response, Marketplace $marketplace): array
    {
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
