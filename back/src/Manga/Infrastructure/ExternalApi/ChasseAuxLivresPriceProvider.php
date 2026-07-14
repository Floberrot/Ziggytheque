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
 * ISBN (/prix/{isbn}) lists live offers from many FR merchants (Amazon, Fnac, eBay,
 * Rakuten, momox…), which makes it the richest keyless price source for French volumes.
 *
 * No public API is documented, so this reads the comparison page's HTML through several
 * independent strategies, tried in order until one yields offers:
 *   1. JSON-LD — a schema.org Product/Offer block embedded in the page;
 *   2. DOM rows — table rows, data-merchant/seller/vendor attributes, "offer"-classed
 *      blocks, then list items — each holding a merchant, a € amount and a link;
 *   3. tolerant text fallback — a known merchant name followed closely by "xx,xx €".
 *
 * A 200 response that still parses to 0 offers logs a WARNING (prod diagnostic: the
 * page layout changed and the strategies need tuning). Failures of any kind degrade
 * to []. The GetVolumePrices handler caches results for 24h per ISBN, so the site is
 * hit at most once per volume per day. Set CHASSE_AUX_LIVRES_BASE_URL="" to disable
 * the provider entirely.
 */
final readonly class ChasseAuxLivresPriceProvider implements VolumePriceProviderInterface
{
    private const string LOG_PREFIX = 'CHASSE_AUX_LIVRES PRICES : ';
    private const string USER_AGENT = 'Ziggytheque/1.0 (+https://www.ziggytheque.fr)';
    private const int MAX_OFFERS = 10;

    /** Row-selection strategies, tried in order until one produces at least one offer. */
    private const array ROW_XPATH_STRATEGIES = [
        '//tr[.//a]',
        '//*[@data-merchant or @data-seller or @data-vendor]',
        "//*[contains(@class, 'offer')][.//a]",
        '//li[.//a]',
    ];

    /** Attributes that may carry the merchant name directly on a row or a descendant. */
    private const array MERCHANT_DATA_ATTRIBUTES = ['data-merchant', 'data-seller', 'data-vendor'];

    /** Merchant names the tolerant text fallback recognizes next to a "xx,xx €" amount. */
    private const array KNOWN_MERCHANTS = [
        'amazon',
        'fnac',
        'ebay',
        'rakuten',
        'momox',
        'cultura',
        'decitre',
        'leslibraires',
        'recyclivre',
        'gibert',
    ];

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

        $offers = $this->parseOffers($response->getContent());

        if ($offers === []) {
            // Prod diagnostic: reachable page but nothing parsed — layout probably changed.
            $this->logger->warning(self::LOG_PREFIX . 'findOffers; 200 OK BUT 0 OFFER PARSED.', [
                'isbn' => $isbn->value,
                'url'  => $url,
            ]);
        }

        return $offers;
    }

    /** @return list<PriceOfferDto> */
    private function parseOffers(string $html): array
    {
        if ($html === '') {
            return [];
        }

        $offers = $this->parseJsonLdOffers($html);

        if ($offers === []) {
            $offers = $this->parseDomOffers($html);
        }

        if ($offers === []) {
            $offers = $this->parseTextOffers($html);
        }

        return array_slice($offers, 0, self::MAX_OFFERS);
    }

    // ── Strategy 1 : JSON-LD (schema.org Product / Offer) ────────────────────

    /** @return list<PriceOfferDto> */
    private function parseJsonLdOffers(string $html): array
    {
        $matchCount = preg_match_all(
            '#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#si',
            $html,
            $matches,
        );

        if ($matchCount === false || $matchCount === 0) {
            return [];
        }

        $offers = [];
        foreach ($matches[1] as $jsonBlock) {
            $decoded = json_decode(trim($jsonBlock), true);
            if (!is_array($decoded)) {
                continue;
            }

            foreach ($this->collectJsonLdOfferNodes($decoded) as $offerNode) {
                $offer = $this->buildJsonLdOffer($offerNode);
                if ($offer !== null) {
                    $offers[] = $offer;
                }
            }
        }

        return $offers;
    }

    /**
     * Walks a decoded JSON-LD document ("@graph" included) and collects every node
     * found under an "offers" key, normalized to a list.
     *
     * @param  array<mixed> $node
     * @return list<array<string, mixed>>
     */
    private function collectJsonLdOfferNodes(array $node): array
    {
        $offerNodes = [];

        $rawOffers = $node['offers'] ?? null;
        if (is_array($rawOffers)) {
            // Either a single offer object or a list of offers.
            $offerList = array_is_list($rawOffers) ? $rawOffers : [$rawOffers];
            foreach ($offerList as $offerCandidate) {
                if (is_array($offerCandidate)) {
                    $offerNodes[] = $offerCandidate;
                }
            }
        }

        foreach (['@graph', 'itemListElement', 'item'] as $childKey) {
            $children = $node[$childKey] ?? null;
            if (!is_array($children)) {
                continue;
            }
            $childList = array_is_list($children) ? $children : [$children];
            foreach ($childList as $child) {
                if (is_array($child)) {
                    $offerNodes = array_merge($offerNodes, $this->collectJsonLdOfferNodes($child));
                }
            }
        }

        return $offerNodes;
    }

    /** @param array<string, mixed> $offerNode */
    private function buildJsonLdOffer(array $offerNode): ?PriceOfferDto
    {
        $rawPrice = $offerNode['price'] ?? ($offerNode['priceSpecification']['price'] ?? null);
        if (!is_string($rawPrice) && !is_int($rawPrice) && !is_float($rawPrice)) {
            return null;
        }

        $amount = (float) str_replace(',', '.', (string) $rawPrice);
        if ($amount <= 0.0) {
            return null;
        }

        $sellerName = $offerNode['seller']['name'] ?? null;
        if (!is_string($sellerName) || trim($sellerName) === '') {
            return null;
        }

        $currency = $offerNode['priceCurrency'] ?? 'EUR';
        $offerUrl = $offerNode['url'] ?? null;

        return new PriceOfferDto(
            kind:         PriceKindEnum::MerchantLive,
            merchant:     mb_substr(trim($sellerName), 0, 60),
            merchantLogo: 'chasseauxlivres',
            amount:       round($amount, 2),
            currency:     is_string($currency) && $currency !== '' ? $currency : 'EUR',
            url:          is_string($offerUrl) ? $this->absolutizeUrl($offerUrl) : null,
            imageUrl:     null,
            source:       'chasse_aux_livres',
        );
    }

    // ── Strategy 2 : DOM rows (tables, data-attributes, offer blocks, lists) ──

    /** @return list<PriceOfferDto> */
    private function parseDomOffers(string $html): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);

        foreach (self::ROW_XPATH_STRATEGIES as $rowExpression) {
            $rows = $xpath->query($rowExpression);
            if ($rows === false || $rows->length === 0) {
                continue;
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

            if ($offers !== []) {
                return $offers;
            }
        }

        return [];
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
        // Highest-fidelity source: an explicit data attribute on the row or a descendant.
        $dataAttributeMerchant = $this->extractMerchantFromDataAttributes($row, $xpath);
        if ($dataAttributeMerchant !== null) {
            return $dataAttributeMerchant;
        }

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

    private function extractMerchantFromDataAttributes(DOMElement $row, DOMXPath $xpath): ?string
    {
        foreach (self::MERCHANT_DATA_ATTRIBUTES as $attributeName) {
            $attributeValue = trim($row->getAttribute($attributeName));
            if ($attributeValue !== '') {
                return mb_substr($attributeValue, 0, 60);
            }
        }

        $descendants = $xpath->query('.//*[@data-merchant or @data-seller or @data-vendor]', $row);
        if ($descendants === false) {
            return null;
        }

        foreach ($descendants as $descendant) {
            if (!$descendant instanceof DOMElement) {
                continue;
            }
            foreach (self::MERCHANT_DATA_ATTRIBUTES as $attributeName) {
                $attributeValue = trim($descendant->getAttribute($attributeName));
                if ($attributeValue !== '') {
                    return mb_substr($attributeValue, 0, 60);
                }
            }
        }

        return null;
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

            return $this->absolutizeUrl($href);
        }

        return null;
    }

    private function absolutizeUrl(string $href): string
    {
        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $this->baseUrl . $href;
        }

        return $href;
    }

    // ── Strategy 3 : tolerant text fallback (known merchant near "xx,xx €") ──

    /** @return list<PriceOfferDto> */
    private function parseTextOffers(string $html): array
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

        $offers = [];
        foreach (self::KNOWN_MERCHANTS as $merchantName) {
            $pattern = sprintf(
                '/(%s)[^€]{0,160}?(\d{1,4})[,.](\d{2})\s*€/iu',
                preg_quote($merchantName, '/'),
            );

            if (preg_match($pattern, $text, $matches) !== 1) {
                continue;
            }

            $offers[] = new PriceOfferDto(
                kind:         PriceKindEnum::MerchantLive,
                merchant:     $matches[1],
                merchantLogo: 'chasseauxlivres',
                amount:       (float) ($matches[2] . '.' . $matches[3]),
                currency:     'EUR',
                url:          null,
                imageUrl:     null,
                source:       'chasse_aux_livres',
            );
        }

        return $offers;
    }
}
