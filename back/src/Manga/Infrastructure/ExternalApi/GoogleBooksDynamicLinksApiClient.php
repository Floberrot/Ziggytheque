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
 * Keyless cover lookup via Google's public Dynamic Links endpoint
 * (books.google.com/books?bibkeys=ISBN:...&jscmd=viewapi). Unlike the Google
 * Books *API* (googleapis.com), it needs no key and isn't quota-metered.
 *
 * Crucially, the response only carries a `thumbnail_url` when Google actually
 * has a cover for that ISBN — so this avoids the "image not available"
 * placeholder that the raw /books/content endpoint returns for missing covers.
 */
final readonly class GoogleBooksDynamicLinksApiClient implements MangaCoverProviderInterface
{
    private const string PREFIX_LOGGER = 'GOOGLE_BOOKS_DYNAMIC_LINKS : ';
    private const string CALLBACK = 'gbcb';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private LoggerInterface $logger,
    ) {
    }

    public function findByIsbn(Isbn $isbn): ?MangaVolumeCoverDto
    {
        $bibKey = 'ISBN:' . $isbn->value;
        $url = sprintf(
            '%s/books?bibkeys=%s&jscmd=viewapi&callback=%s',
            $this->baseUrl,
            $bibKey,
            self::CALLBACK,
        );

        $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; BEGIN.', ['isbn' => $isbn->value]);

        try {
            $response = $this->httpClient->request('GET', $url);

            if ($response->getStatusCode() !== 200) {
                $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; NOT FOUND.', [
                    'isbn' => $isbn->value,
                    'status' => $response->getStatusCode(),
                ]);
                return null;
            }

            $payload = $this->decodeJsonp($response->getContent());
            $thumbnailUrl = $payload[$bibKey]['thumbnail_url'] ?? null;

            if (!is_string($thumbnailUrl) || $thumbnailUrl === '') {
                $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; NO COVER.', ['isbn' => $isbn->value]);
                return null;
            }

            $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; FOUND.', ['isbn' => $isbn->value]);

            return new MangaVolumeCoverDto(
                coverUrl: $this->upgradeThumbnail($thumbnailUrl),
                spineUrl: null,
                isbn: $isbn,
                source: 'google_books',
            );
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

    /**
     * The Dynamic Links endpoint replies with JSONP: `gbcb({...});`.
     *
     * @return array<string, mixed>
     */
    private function decodeJsonp(string $body): array
    {
        $start = strpos($body, '(');
        $end = strrpos($body, ')');

        if ($start === false || $end === false || $end <= $start) {
            return [];
        }

        $decoded = json_decode(substr($body, $start + 1, $end - $start - 1), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** Promote the small zoom=5 thumbnail to a larger, curl-free cover image. */
    private function upgradeThumbnail(string $thumbnailUrl): string
    {
        $url = str_replace('http://', 'https://', $thumbnailUrl);
        $url = preg_replace('/([?&])zoom=\d+/', '${1}zoom=1', $url) ?? $url;

        return implode('', explode('&edge=curl', $url));
    }
}
