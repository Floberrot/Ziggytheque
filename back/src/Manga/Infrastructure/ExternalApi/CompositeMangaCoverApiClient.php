<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaCoverProviderInterface;
use App\Manga\Domain\MangaVolumeCoverDto;
use Psr\Log\LoggerInterface;

final readonly class CompositeMangaCoverApiClient implements MangaCoverProviderInterface
{
    /**
     * @param iterable<MangaCoverProviderInterface> $providers ordered by priority (highest first)
     */
    public function __construct(
        private iterable $providers,
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
