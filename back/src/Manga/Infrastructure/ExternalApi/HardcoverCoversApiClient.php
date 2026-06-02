<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaCoverProviderInterface;
use App\Manga\Domain\MangaVolumeCoverDto;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Cover lookup via the Hardcover GraphQL API (https://hardcover.app).
 *
 * Hardcover re-hosts cover images on its own CDN (assets.hardcover.app), so it
 * can surface covers that rights-protected publishers hide from Google. It needs
 * a personal Bearer token; without one the client is a no-op (returns null and
 * makes no request), so it stays harmless in the cascade until HARDCOVER_API_TOKEN
 * is configured.
 */
final readonly class HardcoverCoversApiClient implements MangaCoverProviderInterface
{
    private const string PREFIX_LOGGER = 'HARDCOVER : ';
    private const string QUERY = 'query CoverByIsbn($isbn: String!) {'
        . ' editions(where: {isbn_13: {_eq: $isbn}}, limit: 5) { image { url } } }';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private string $apiToken,
        private LoggerInterface $logger,
    ) {
    }

    public function findByIsbn(Isbn $isbn): ?MangaVolumeCoverDto
    {
        if (trim($this->apiToken) === '') {
            return null;
        }

        $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; BEGIN.', ['isbn' => $isbn->value]);

        // Hardcover's settings page shows the value already prefixed with "Bearer ";
        // strip it so a copy-pasted token doesn't become "Bearer Bearer …" → 401.
        $token = trim(preg_replace('/^\s*Bearer\s+/i', '', $this->apiToken) ?? $this->apiToken);

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'query' => self::QUERY,
                    'variables' => ['isbn' => $isbn->value],
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; NOT FOUND.', [
                    'isbn' => $isbn->value,
                    'status' => $response->getStatusCode(),
                ]);
                return null;
            }

            $editions = $response->toArray()['data']['editions'] ?? [];

            foreach ($editions as $edition) {
                $coverUrl = $edition['image']['url'] ?? null;
                if (is_string($coverUrl) && $coverUrl !== '') {
                    $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; FOUND.', ['isbn' => $isbn->value]);

                    return new MangaVolumeCoverDto(
                        coverUrl: $coverUrl,
                        spineUrl: null,
                        isbn: $isbn,
                        source: 'hardcover',
                    );
                }
            }

            $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; NO COVER.', ['isbn' => $isbn->value]);
            return null;
        } catch (Throwable $exception) {
            $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; ERROR.', [
                'isbn' => $isbn->value,
                'error' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    public function findByContext(
        string $mangaTitle,
        ?string $edition,
        int $volumeNumber,
        string $language = 'fr',
    ): ?MangaVolumeCoverDto {
        // ISBN-only provider; title-based search is handled by the other providers.
        return null;
    }
}
