<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\Marketplace;
use App\Manga\Domain\PriceKindEnum;
use App\Manga\Domain\PriceOfferDto;
use App\Manga\Domain\VolumePriceProviderInterface;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Psr\Log\LoggerInterface;
use Throwable;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Chasse aux livres (chasse-aux-livres.fr) — French book price comparator. One page per
 * ISBN (/prix/{isbn}) lists live offers from many FR merchants (Amazon, Fnac, Rakuten,
 * momox…), which makes it the richest keyless price source for French volumes.
 *
 * No public API is documented, so this reads the comparison page's HTML. The parser is
 * deliberately tolerant (any row-like block holding a link and a € amount) because the
 * exact markup could not be verified from the development sandbox — if offers come back
 * empty against the real site, only {@see self::parseOffers} needs tuning. Failures of
 * any kind degrade to []. The GetVolumePrices handler caches results for 24h per ISBN,
 * so the site is hit at most once per volume per day. Set CHASSE_AUX_LIVRES_BASE_URL=""
 * to disable the provider entirely.
 */
final readonly class ChasseAuxLivresPriceProvider implements VolumePriceProviderInterface
{
    private const string LOG_PREFIX = 'CHASSE_AUX_LIVRES PRICES : ';
    private const string USER_AGENT = 'Ziggytheque/1.0 (+https://www.ziggytheque.fr)';
    private const int MAX_OFFERS = 10;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private LoggerInterface $logger,
    ) {
    }

    public function findOffers(Isbn $isbn, Marketplace $marketplace): array
    {
        if ($this->baseUrl === '') {
            return [];
        }

        // French comparator, EUR prices — irrelevant for other marketplaces.
        if ($marketplace !== Marketplace::Fr) {
            return [];
        }

        $this->logger->info(self::LOG_PREFIX . 'findOffers; BEGIN.', ['isbn' => $isbn->value]);

        try {
            return $this->doFindOffers($isbn);
        } catch (Throwable $exception) {
            $this->logger->error(self::LOG_PREFIX . 'findOffers; ERROR.', [
                'isbn'  => $isbn->value,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /** @return list<PriceOfferDto> */
    private function doFindOffers(Isbn $isbn): array
    {
        $url      = sprintf('%s/prix/%s', $this->baseUrl, $isbn->value);
        $response = $this->httpClient->request('GET', $url, [
            'headers' => ['User-Agent' => self::USER_AGENT],
        ]);

        if ($response->getStatusCode() !== 200) {
            $this->logger->info(self::LOG_PREFIX . 'findOffers; NOT 200.', [
                'status' => $response->getStatusCode(),
            ]);

            return [];
        }

        return $this->parseOffers($response->getContent());
    }

    /** @return list<PriceOfferDto> */
    private function parseOffers(string $html): array
    {
        if ($html === '') {
            return [];
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);

        // Comparison rows: a table row holding a merchant link and a € amount. Falls
        // back to any "offer"-classed block for a non-table layout.
        $rows = $xpath->query('//tr[.//a]');
        if ($rows === false || $rows->length === 0) {
            $rows = $xpath->query("//*[contains(@class, 'offer')][.//a]");
        }
        if ($rows === false) {
            return [];
        }

        $offers = [];
        foreach ($rows as $row) {
            if (!$row instanceof DOMElement) {
                continue;
            }
            $offer = $this->parseRow($row, $xpath);
            if ($offer !== null) {
                $offers[] = $offer;
            }
            if (count($offers) >= self::MAX_OFFERS) {
                break;
            }
        }

        return $offers;
    }

    private function parseRow(DOMElement $row, DOMXPath $xpath): ?PriceOfferDto
    {
        $amount = $this->extractAmount($row->textContent);
        if ($amount === null) {
            return null;
        }

        $merchant = $this->extractMerchant($row, $xpath);
        if ($merchant === null) {
            return null;
        }

        return new PriceOfferDto(
            kind:         PriceKindEnum::MerchantLive,
            merchant:     $merchant,
            merchantLogo: 'chasseauxlivres',
            amount:       $amount,
            currency:     'EUR',
            url:          $this->extractUrl($row, $xpath),
            imageUrl:     null,
            source:       'chasse_aux_livres',
        );
    }

    private function extractAmount(string $text): ?float
    {
        // French price format: "6,50 €" (comma decimals, € after the amount).
        if (preg_match('/(\d{1,4})[,.](\d{2})\s*€/u', $text, $matches) !== 1) {
            return null;
        }

        return (float) ($matches[1] . '.' . $matches[2]);
    }

    private function extractMerchant(DOMElement $row, DOMXPath $xpath): ?string
    {
        // Merchants are usually shown as a logo — the alt text names them.
        $images = $xpath->query('.//img[@alt]', $row);
        if ($images !== false) {
            foreach ($images as $image) {
                if (!$image instanceof DOMElement) {
                    continue;
                }
                $alt = trim($image->getAttribute('alt'));
                if ($alt !== '') {
                    return mb_substr($alt, 0, 60);
                }
            }
        }

        // Text fallback: the first short text chunk of the row.
        $firstChunk = trim((string) strtok(trim($row->textContent), "\n"));
        $firstChunk = trim((string) preg_replace('/\s+/u', ' ', $firstChunk));

        return ($firstChunk !== '' && mb_strlen($firstChunk) <= 60) ? $firstChunk : null;
    }

    private function extractUrl(DOMElement $row, DOMXPath $xpath): ?string
    {
        $links = $xpath->query('.//a[@href]', $row);
        if ($links === false) {
            return null;
        }

        foreach ($links as $link) {
            if (!$link instanceof DOMElement) {
                continue;
            }
            $href = trim($link->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
                continue;
            }
            if (str_starts_with($href, '//')) {
                return 'https:' . $href;
            }
            if (str_starts_with($href, '/')) {
                return $this->baseUrl . $href;
            }

            return $href;
        }

        return null;
    }
}
