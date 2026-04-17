<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class AmazonBooksMangaApiClient implements ExternalApiClientInterface
{
    private const BASE_URL = 'https://advertising-api.amazon.com/v1/sp/products/search';
    private const SERVICE = 'advertising-api';
    private const REGION = 'us-east-1';
    private const ALGORITHM = 'AWS4-HMAC-SHA256';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $accessKey,
        private string $secretKey,
        private string $partnerTag,
        private string $marketplace,
    ) {
    }

    /**
     * @return ExternalMangaDto[]
     */
    public function searchByTitle(string $query, string $type = 'manga', int $page = 1): array
    {
        $timestamp = (new \DateTime('UTC'))->format('Ymd\THis\Z');
        $dateStamp = (new \DateTime('UTC'))->format('Ymd');

        $body = json_encode([
            'Keywords' => $query,
            'SearchIndex' => 'Books',
            'Marketplace' => $this->marketplace,
            'ItemCount' => 20,
            'ItemPage' => $page,
        ], JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => $this->signRequest($body, $timestamp, $dateStamp),
            'X-Amzn-Date' => $timestamp,
        ];

        $response = $this->httpClient->request('POST', self::BASE_URL, [
            'body' => $body,
            'headers' => $headers,
        ]);

        $data = $response->toArray();

        if (empty($data['products'])) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (array $item) => $this->mapToDto($item),
            $data['products'],
        )));
    }

    public function getMangaById(string $externalId): ?ExternalMangaDto
    {
        $timestamp = (new \DateTime('UTC'))->format('Ymd\THis\Z');
        $dateStamp = (new \DateTime('UTC'))->format('Ymd');

        $body = json_encode([
            'ItemIds' => [$externalId],
            'PartnerTag' => $this->partnerTag,
            'Resources' => ['Images.Primary.Large'],
        ], JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => $this->signRequest($body, $timestamp, $dateStamp),
            'X-Amzn-Date' => $timestamp,
        ];

        $response = $this->httpClient->request('POST', 'https://api.amazon.com/onca/xml', [
            'body' => $body,
            'headers' => $headers,
        ]);

        $data = $response->toArray();

        if (empty($data['Items'][0])) {
            return null;
        }

        return $this->mapToDto($data['Items'][0]);
    }

    /** @param array<string, mixed> $item */
    private function mapToDto(array $item): ?ExternalMangaDto
    {
        $title = $item['ItemInfo']['Title']['DisplayValue'] ?? null;
        if ($title === null) {
            return null;
        }

        $coverUrl = $item['Images']['Primary']['Large']['URL'] ?? null;
        $author = $item['ItemInfo']['ByLineInfo']['Contributors'][0]['Name'] ?? null;
        $edition = $item['ItemInfo']['ManufactureInfo']['Manufacturer'] ?? null;
        $language = 'fr';
        $summary = $item['ItemInfo']['Features']['DisplayValues'][0] ?? null;
        $genre = null;

        return new ExternalMangaDto(
            externalId: $item['ASIN'],
            title: $title,
            edition: $edition,
            author: $author,
            summary: $summary,
            coverUrl: $coverUrl,
            genre: $genre,
            language: $language,
            source: 'amazon',
        );
    }

    private function signRequest(string $body, string $timestamp, string $dateStamp): string
    {
        $canonicalRequest = $this->createCanonicalRequest($body, $timestamp);
        $stringToSign = $this->createStringToSign($canonicalRequest, $timestamp, $dateStamp);
        $signature = $this->calculateSignature($stringToSign, $dateStamp);

        return sprintf(
            '%s Credential=%s/%s/%s/%s/aws4_request, SignedHeaders=%s, Signature=%s',
            self::ALGORITHM,
            $this->accessKey,
            $dateStamp,
            self::REGION,
            self::SERVICE,
            'content-type;host;x-amzn-date',
            $signature,
        );
    }

    private function createCanonicalRequest(string $body, string $timestamp): string
    {
        $payloadHash = hash('sha256', $body);

        return implode("\n", [
            'POST',
            '/v1/sp/products/search',
            '',
            'content-type:application/json',
            'host:advertising-api.amazon.com',
            'x-amzn-date:' . $timestamp,
            '',
            'content-type;host;x-amzn-date',
            $payloadHash,
        ]);
    }

    private function createStringToSign(string $canonicalRequest, string $timestamp, string $dateStamp): string
    {
        $canonicalRequestHash = hash('sha256', $canonicalRequest);

        return implode("\n", [
            self::ALGORITHM,
            $timestamp,
            $dateStamp . '/' . self::REGION . '/' . self::SERVICE . '/aws4_request',
            $canonicalRequestHash,
        ]);
    }

    private function calculateSignature(string $stringToSign, string $dateStamp): string
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', self::REGION, $kDate, true);
        $kService = hash_hmac('sha256', self::SERVICE, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        return hash_hmac('sha256', $stringToSign, $kSigning);
    }
}
