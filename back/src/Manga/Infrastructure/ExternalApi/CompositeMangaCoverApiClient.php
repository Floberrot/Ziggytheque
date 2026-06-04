<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaCoverProviderInterface;
use App\Manga\Domain\MangaVolumeCoverDto;
use App\Manga\Domain\MultiContextCoverProviderInterface;
use App\Manga\Domain\MultiSourceCoverProviderInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class CompositeMangaCoverApiClient implements
    MangaCoverProviderInterface,
    MultiSourceCoverProviderInterface
{
    /**
     * @param iterable<MangaCoverProviderInterface>        $providers        ISBN/cover cascade, highest priority first
     * @param iterable<MultiContextCoverProviderInterface> $contextProviders title-search sources
     */
    public function __construct(
        private iterable $providers,
        private iterable $contextProviders,
        private LoggerInterface $logger,
    ) {
    }

    public function findByIsbn(Isbn $isbn): ?MangaVolumeCoverDto
    {
        foreach ($this->providers as $provider) {
            $providerClass = $provider::class;
            $this->logger->info('COMPOSITE : trying findByIsbn.', [
                'provider' => $providerClass,
                'isbn' => $isbn->value,
            ]);

            $result = $provider->findByIsbn($isbn);

            $this->logger->info('COMPOSITE : findByIsbn result.', [
                'provider' => $providerClass,
                'match' => $result !== null,
            ]);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    public function findAllByIsbn(Isbn $isbn): array
    {
        $covers = [];

        foreach ($this->providers as $provider) {
            $result = $provider->findByIsbn($isbn);

            $this->logger->info('COMPOSITE : findAllByIsbn source result.', [
                'provider' => $provider::class,
                'match' => $result !== null,
            ]);

            if ($result !== null) {
                $covers[] = $result;
            }
        }

        return $covers;
    }

    public function findAllByContext(
        string $mangaTitle,
        ?string $edition,
        int $volumeNumber,
        string $language = 'fr',
    ): array {
        $covers = [];

        foreach ($this->contextProviders as $provider) {
            try {
                $providerCovers = $provider->findAllByContext($mangaTitle, $edition, $volumeNumber, $language);

                $this->logger->info('COMPOSITE : findAllByContext source result.', [
                    'provider' => $provider::class,
                    'count' => count($providerCovers),
                ]);

                foreach ($providerCovers as $cover) {
                    $covers[] = $cover;
                }
            } catch (Throwable $exception) {
                $this->logger->error('COMPOSITE : findAllByContext provider failed, skipping.', [
                    'provider' => $provider::class,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $covers;
    }

    public function findByContext(
        string $mangaTitle,
        ?string $edition,
        int $volumeNumber,
        string $language = 'fr',
    ): ?MangaVolumeCoverDto {
        foreach ($this->providers as $provider) {
            $providerClass = $provider::class;
            $this->logger->info('COMPOSITE : trying findByContext.', [
                'provider' => $providerClass,
                'title' => $mangaTitle,
                'volume' => $volumeNumber,
            ]);

            $result = $provider->findByContext($mangaTitle, $edition, $volumeNumber, $language);

            $this->logger->info('COMPOSITE : findByContext result.', [
                'provider' => $providerClass,
                'match' => $result !== null,
            ]);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }
}
