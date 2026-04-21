<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;
use App\Manga\Domain\ExternalVolumeDto;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class MangaDexApiClient implements ExternalApiClientInterface
{
    private const string BASE_URL    = 'https://api.mangadex.org';
    private const string UPLOADS_URL = 'https://uploads.mangadex.org/covers';
    private const string LOG_PREFIX  = '[MangaDex] ';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    /** @return ExternalMangaDto[] */
    public function searchByTitle(string $query, string $type = 'manga', int $page = 1): array
    {
        $this->logger->info(self::LOG_PREFIX . 'searchByTitle: start', [
            'query' => $query, 'type' => $type, 'page' => $page,
        ]);

        $response = $this->httpClient->request('GET', self::BASE_URL . '/manga', [
            'query' => [
                'title'            => $query,
                'limit'            => 20,
                'offset'           => ($page - 1) * 20,
                'includes'         => ['cover_art', 'author'],
                'contentRating'    => ['safe', 'suggestive', 'erotica'],
                'order'            => ['relevance' => 'desc'],
            ],
        ]);

        $data  = $response->toArray();
        $items = $data['data'] ?? [];

        $results = array_values(array_filter(array_map(
            fn (array $item) => $this->mapMangaToDto($item),
            $items,
        )));

        $this->logger->info(self::LOG_PREFIX . 'searchByTitle: done', [
            'query'  => $query,
            'raw'    => count($items),
            'mapped' => count($results),
        ]);

        return $results;
    }

    public function getMangaById(string $externalId): ?ExternalMangaDto
    {
        $this->logger->info(self::LOG_PREFIX . 'getMangaById: start', ['id' => $externalId]);

        $response = $this->httpClient->request(
            'GET',
            self::BASE_URL . '/manga/' . $externalId,
            ['query' => ['includes' => ['cover_art', 'author']]],
        );

        $data = $response->toArray();
        $item = $data['data'] ?? null;

        if ($item === null) {
            $this->logger->warning(self::LOG_PREFIX . 'getMangaById: no data in response', ['id' => $externalId]);
            return null;
        }

        $dto = $this->mapMangaToDto($item);
        if ($dto === null) {
            return null;
        }

        $volumes = $this->getVolumeCovers($externalId);

        $this->logger->info(self::LOG_PREFIX . 'getMangaById: done', [
            'id'      => $externalId,
            'title'   => $dto->title,
            'volumes' => count($volumes),
        ]);

        return new ExternalMangaDto(
            externalId:   $dto->externalId,
            title:        $dto->title,
            edition:      $dto->edition,
            author:       $dto->author,
            summary:      $dto->summary,
            coverUrl:     $dto->coverUrl,
            genre:        $dto->genre,
            language:     $dto->language,
            source:       $dto->source,
            totalVolumes: $dto->totalVolumes,
            volumes:      $volumes,
        );
    }

    /** @return ExternalVolumeDto[] */
    public function getVolumeCovers(string $externalId): array
    {
        $this->logger->info(self::LOG_PREFIX . 'getVolumeCovers: start', ['id' => $externalId]);

        $response = $this->httpClient->request('GET', self::BASE_URL . '/cover', [
            'query' => [
                'manga'  => [$externalId],
                'limit'  => 100,
                'order'  => ['volume' => 'asc'],
            ],
        ]);

        $data    = $response->toArray();
        $volumes = [];

        foreach ($data['data'] ?? [] as $cover) {
            $attrs     = $cover['attributes'] ?? [];
            $volumeNum = isset($attrs['volume']) && $attrs['volume'] !== '' ? (int) $attrs['volume'] : null;
            if ($volumeNum === null) {
                continue;
            }

            $fileName = $attrs['fileName'] ?? null;
            $coverUrl = $fileName !== null
                ? self::UPLOADS_URL . '/' . $externalId . '/' . $fileName . '.512.jpg'
                : null;

            $releaseDate = null;
            if (!empty($attrs['createdAt'])) {
                try {
                    $releaseDate = new \DateTimeImmutable($attrs['createdAt']);
                } catch (Throwable) {
                }
            }

            $volumes[] = new ExternalVolumeDto(
                number:      $volumeNum,
                coverUrl:    $coverUrl,
                releaseDate: $releaseDate,
            );
        }

        $this->logger->info(self::LOG_PREFIX . 'getVolumeCovers: done', [
            'id'      => $externalId,
            'volumes' => count($volumes),
        ]);

        return $volumes;
    }

    /** @param array<string, mixed> $item */
    private function mapMangaToDto(array $item): ?ExternalMangaDto
    {
        $id    = $item['id'] ?? null;
        $attrs = $item['attributes'] ?? [];
        if ($id === null) {
            return null;
        }

        $title = $this->extractTitle($attrs);
        if ($title === null) {
            $this->logger->debug(self::LOG_PREFIX . 'mapMangaToDto: skipped (no title)', ['id' => $id]);
            return null;
        }

        $relationships = $item['relationships'] ?? [];

        return new ExternalMangaDto(
            externalId:   $id,
            title:        $title,
            edition:      null,
            author:       $this->extractAuthor($relationships),
            summary:      $this->extractDescription($attrs),
            coverUrl:     $this->extractMainCoverUrl($id, $relationships),
            genre:        $this->extractGenre($attrs),
            language:     'fr',
            source:       'mangadex',
            totalVolumes: isset($attrs['lastVolume']) && $attrs['lastVolume'] !== ''
                ? (int) $attrs['lastVolume']
                : null,
        );
    }

    /** @param array<string, mixed> $attrs */
    private function extractTitle(array $attrs): ?string
    {
        $titles = $attrs['title'] ?? [];
        return $titles['fr'] ?? $titles['en'] ?? $titles['ja-ro']
            ?? (count($titles) > 0 ? array_values($titles)[0] : null);
    }

    /** @param array<string, mixed> $attrs */
    private function extractDescription(array $attrs): ?string
    {
        $desc = $attrs['description'] ?? [];
        return $desc['fr'] ?? $desc['en']
            ?? (count($desc) > 0 ? array_values($desc)[0] : null);
    }

    /**
     * @param array<int, array<string, mixed>> $rels
     */
    private function extractMainCoverUrl(string $mangaId, array $rels): ?string
    {
        foreach ($rels as $rel) {
            if (($rel['type'] ?? '') !== 'cover_art') {
                continue;
            }
            $fileName = $rel['attributes']['fileName'] ?? null;
            if ($fileName !== null) {
                return self::UPLOADS_URL . '/' . $mangaId . '/' . $fileName . '.512.jpg';
            }
        }
        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $rels
     */
    private function extractAuthor(array $rels): ?string
    {
        $names = [];
        foreach ($rels as $rel) {
            if (($rel['type'] ?? '') !== 'author') {
                continue;
            }
            $name = $rel['attributes']['name'] ?? null;
            if ($name !== null) {
                $names[] = $name;
            }
        }
        return $names !== [] ? implode(', ', $names) : null;
    }

    /** @param array<string, mixed> $attrs */
    private function extractGenre(array $attrs): string
    {
        foreach ($attrs['tags'] ?? [] as $tag) {
            if (($tag['attributes']['group'] ?? '') !== 'demographic') {
                continue;
            }
            $name   = strtolower($tag['attributes']['name']['en'] ?? '');
            $mapped = match (true) {
                str_contains($name, 'shounen') || str_contains($name, 'shonen') => 'shonen',
                str_contains($name, 'shoujo')  || str_contains($name, 'shojo')  => 'shojo',
                str_contains($name, 'seinen')  => 'seinen',
                str_contains($name, 'josei')   => 'josei',
                default                        => null,
            };
            if ($mapped !== null) {
                return $mapped;
            }
        }

        foreach ($attrs['tags'] ?? [] as $tag) {
            $name   = strtolower($tag['attributes']['name']['en'] ?? '');
            $mapped = match (true) {
                str_contains($name, 'isekai')                                            => 'isekai',
                str_contains($name, 'action')                                            => 'action',
                str_contains($name, 'romance')                                           => 'romance',
                str_contains($name, 'horror')                                            => 'horror',
                str_contains($name, 'fantasy')                                           => 'fantasy',
                str_contains($name, 'sci-fi') || str_contains($name, 'science fiction')  => 'sci_fi',
                str_contains($name, 'sport')                                             => 'sports',
                default                                                                  => null,
            };
            if ($mapped !== null) {
                return $mapped;
            }
        }

        return 'other';
    }
}
