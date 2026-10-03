<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaVolumeCoverDto;
use Closure;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

final readonly class OpenLibraryCoversApiClient implements DeferredIsbnCoverProviderInterface
{
    private const string PREFIX_LOGGER = 'OPEN_LIBRARY : ';
    private const int MIN_COVER_CONTENT_LENGTH = 2000;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private LoggerInterface $logger,
    ) {
    }

    public function findByIsbn(Isbn $isbn): ?MangaVolumeCoverDto
    {
        return $this->requestByIsbn($isbn)();
    }

    public function requestByIsbn(Isbn $isbn): Closure
    {
        $coverUrl = sprintf('%s/b/isbn/%s-L.jpg?default=false', $this->baseUrl, $isbn->value);

        $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; BEGIN.', ['isbn' => $isbn->value]);

        try {
            $response = $this->httpClient->request('HEAD', $coverUrl);
        } catch (Throwable $exception) {
            $this->logError($isbn, $exception);

            return static fn (): ?MangaVolumeCoverDto => null;
        }

        return function () use ($isbn, $coverUrl, $response): ?MangaVolumeCoverDto {
            try {
                return $this->readCover($isbn, $coverUrl, $response);
            } catch (Throwable $exception) {
                $this->logError($isbn, $exception);

                return null;
            }
        };
    }

    private function readCover(Isbn $isbn, string $coverUrl, ResponseInterface $response): ?MangaVolumeCoverDto
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; NOT FOUND.', [
                'isbn' => $isbn->value,
                'status' => $statusCode,
            ]);
            return null;
        }

        $contentLength = $this->resolveContentLength($response->getHeaders(), $coverUrl);
        if ($contentLength < self::MIN_COVER_CONTENT_LENGTH) {
            $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; IMAGE TOO SMALL.', [
                'isbn' => $isbn->value,
                'content_length' => $contentLength,
            ]);
            return null;
        }

        $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; FOUND.', ['isbn' => $isbn->value]);

        return new MangaVolumeCoverDto(
            coverUrl: $coverUrl,
            isbn: $isbn,
            source: 'open_library',
        );
    }

    private function logError(Isbn $isbn, Throwable $exception): void
    {
        $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; ERROR.', [
            'isbn' => $isbn->value,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Reads the content-length announced by the HEAD response. Some responses omit the
     * header entirely — treating that as 0 would wrongly reject a perfectly valid
     * cover, so fall back to a real GET and measure the actual body instead.
     *
     * @param array<string, list<string>> $headers
     */
    private function resolveContentLength(array $headers, string $coverUrl): int
    {
        $rawContentLength = $headers['content-length'][0] ?? null;
        if ($rawContentLength !== null && is_numeric($rawContentLength) && (int) $rawContentLength > 0) {
            return (int) $rawContentLength;
        }

        $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; NO CONTENT-LENGTH ON HEAD, FALLING BACK TO GET.');

        $getResponse = $this->httpClient->request('GET', $coverUrl);
        if ($getResponse->getStatusCode() !== 200) {
            return 0;
        }

        return strlen($getResponse->getContent());
    }

    public function findByContext(
        string $mangaTitle,
        ?string $edition,
        int $volumeNumber,
        string $language = 'fr',
    ): ?MangaVolumeCoverDto {
        // Open Library does not support title-based cover search in this context
        return null;
    }
}
