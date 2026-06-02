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
 * Cover lookup via the Bibliothèque nationale de France (BnF) catalogue.
 * Keyless and authoritative for French editions (legal deposit), so it covers
 * French manga that Google/OpenLibrary may miss.
 *
 * Two steps: the SRU API resolves the record's ARK identifier from the ISBN,
 * then the catalogue "couverture" service serves the cover. The cover is fetched
 * and validated (real image, large enough) so a missing/placeholder cover yields
 * null rather than a broken image in the SPA.
 */
final readonly class BnfCoversApiClient implements MangaCoverProviderInterface
{
    private const string PREFIX_LOGGER = 'BNF : ';
    private const int MIN_COVER_CONTENT_LENGTH = 2000;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private LoggerInterface $logger,
    ) {
    }

    public function findByIsbn(Isbn $isbn): ?MangaVolumeCoverDto
    {
        $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; BEGIN.', ['isbn' => $isbn->value]);

        try {
            $ark = $this->resolveArk($isbn);
            if ($ark === null) {
                $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; NO RECORD.', ['isbn' => $isbn->value]);
                return null;
            }

            $coverUrl = sprintf('%s/couverture?appName=NE&idArk=%s&couverture=1', $this->baseUrl, $ark);
            if (!$this->isRealCover($coverUrl)) {
                $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; NO COVER.', [
                    'isbn' => $isbn->value,
                    'ark' => $ark,
                ]);
                return null;
            }

            $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; FOUND.', ['isbn' => $isbn->value]);

            return new MangaVolumeCoverDto(
                coverUrl: $coverUrl,
                spineUrl: null,
                isbn: $isbn,
                source: 'bnf',
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

    private function resolveArk(Isbn $isbn): ?string
    {
        $sruUrl = sprintf(
            '%s/api/SRU?version=1.2&operation=searchRetrieve&query=%s&recordSchema=unimarcxchange&maximumRecords=1',
            $this->baseUrl,
            rawurlencode(sprintf('bib.isbn all "%s"', $isbn->value)),
        );

        $response = $this->httpClient->request('GET', $sruUrl);
        if ($response->getStatusCode() !== 200) {
            return null;
        }

        // BnF record identifiers look like ark:/12148/cb45365380x — pull the first one out of the XML.
        if (preg_match('#ark:/12148/cb[0-9a-z]+#', $response->getContent(), $matches) === 1) {
            return $matches[0];
        }

        return null;
    }

    private function isRealCover(string $coverUrl): bool
    {
        $response = $this->httpClient->request('GET', $coverUrl);
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        $contentType = $response->getHeaders()['content-type'][0] ?? '';
        if (!str_starts_with($contentType, 'image/')) {
            return false;
        }

        return strlen($response->getContent()) >= self::MIN_COVER_CONTENT_LENGTH;
    }
}
