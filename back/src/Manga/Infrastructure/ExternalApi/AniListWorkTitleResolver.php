<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\WorkTitleResolverInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Resolves a work's per-language title via the AniList GraphQL API (keyless). AniList
 * indexes every series with its romaji / English / native titles plus synonyms, and its
 * fuzzy search matches localized names — so "L'Attaque des titans" finds the series and
 * yields "進撃の巨人" (ja) or "Attack on Titan" (en/de/es/it).
 */
final readonly class AniListWorkTitleResolver implements WorkTitleResolverInterface
{
    private const string LOG_PREFIX = 'ANILIST TITLE : ';

    private const string GRAPHQL_QUERY =
        'query ($search: String) { Media(search: $search, type: MANGA) '
        . '{ title { romaji english native } synonyms } }';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private LoggerInterface $logger,
    ) {
    }

    public function resolve(string $query, ?string $language): ?string
    {
        // French keeps the user's own wording (French catalogues use it); only foreign
        // markets need a translated title.
        if ($query === '' || $language === null || $language === 'fr') {
            return null;
        }

        try {
            return $this->doResolve($query, $language);
        } catch (Throwable $exception) {
            $this->logger->error(self::LOG_PREFIX . 'resolve; ERROR.', [
                'query' => $query,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function doResolve(string $query, string $language): ?string
    {
        $response = $this->httpClient->request('POST', $this->baseUrl, [
            'json' => [
                'query'     => self::GRAPHQL_QUERY,
                'variables' => ['search' => $query],
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        /** @var array{data?: array{Media?: array{title?: array<string, ?string>}}} $data */
        $data  = json_decode($response->getContent(), true);
        $title = $data['data']['Media']['title'] ?? null;

        if ($title === null) {
            return null;
        }

        $romaji  = $this->cleanString($title['romaji'] ?? null);
        $english = $this->cleanString($title['english'] ?? null);
        $native  = $this->cleanString($title['native'] ?? null);

        return match ($language) {
            'ja'    => $native ?? $romaji,
            default => $english ?? $romaji,
        };
    }

    private function cleanString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
