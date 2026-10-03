<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaCoverProviderInterface;
use App\Manga\Domain\MangaVolumeCoverDto;
use App\Manga\Domain\MultiContextCoverProviderInterface;
use App\Manga\Domain\MultiSourceCoverProviderInterface;
use Closure;
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

    /**
     * The first source with a cover wins, so the sources are asked one after another on
     * purpose: the first one (BnF) answers most lookups, and asking them all at once would
     * multiply the outbound calls — OpenLibrary refuses an IP past 100 ISBN lookups in
     * five minutes — for no gain whenever it does.
     */
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

    /**
     * Every source is asked anyway: all their lookups are sent first, then read in
     * priority order — the sources answer concurrently, the covers keep the same order,
     * and a source that fails or times out only loses its own cover.
     */
    public function findAllByIsbn(Isbn $isbn): array
    {
        /** @var list<array{MangaCoverProviderInterface, Closure(): ?MangaVolumeCoverDto}> $pendingSources */
        $pendingSources = [];
        foreach ($this->providers as $provider) {
            $pendingSources[] = [$provider, $this->startIsbnLookup($provider, $isbn)];
        }

        $covers = [];

        foreach ($pendingSources as [$provider, $readCover]) {
            try {
                $result = $readCover();
            } catch (Throwable $exception) {
                $this->logger->error('COMPOSITE : findAllByIsbn provider failed, skipping.', [
                    'provider' => $provider::class,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

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

    /** Same as {@see findAllByIsbn()}: every source searched at once, read in order. */
    public function findAllByContext(
        string $mangaTitle,
        ?string $edition,
        int $volumeNumber,
        string $language = 'fr',
    ): array {
        /** @var list<array{MultiContextCoverProviderInterface, Closure(): list<MangaVolumeCoverDto>}> $pendingSources */
        $pendingSources = [];
        foreach ($this->contextProviders as $provider) {
            $pendingSources[] = [
                $provider,
                $this->startContextSearch($provider, $mangaTitle, $edition, $volumeNumber, $language),
            ];
        }

        $covers = [];

        foreach ($pendingSources as [$provider, $readCovers]) {
            try {
                $providerCovers = $readCovers();

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

    /** A cascade like {@see findByIsbn()}: one source after another, first cover wins. */
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

    /**
     * Starts one source's ISBN lookup. A source that cannot send it on its own (or sends
     * no request at all) is simply asked when its turn to be read comes.
     *
     * @return Closure(): ?MangaVolumeCoverDto
     */
    private function startIsbnLookup(MangaCoverProviderInterface $provider, Isbn $isbn): Closure
    {
        if (!$provider instanceof DeferredIsbnCoverProviderInterface) {
            return static fn (): ?MangaVolumeCoverDto => $provider->findByIsbn($isbn);
        }

        try {
            return $provider->requestByIsbn($isbn);
        } catch (Throwable $exception) {
            // Reported with the other results, in the source's own turn.
            return static fn (): ?MangaVolumeCoverDto => throw $exception;
        }
    }

    /** @return Closure(): list<MangaVolumeCoverDto> */
    private function startContextSearch(
        MultiContextCoverProviderInterface $provider,
        string $mangaTitle,
        ?string $edition,
        int $volumeNumber,
        string $language,
    ): Closure {
        if (!$provider instanceof DeferredContextCoverProviderInterface) {
            return static fn (): array => $provider->findAllByContext($mangaTitle, $edition, $volumeNumber, $language);
        }

        try {
            return $provider->requestAllByContext($mangaTitle, $edition, $volumeNumber, $language);
        } catch (Throwable $exception) {
            return static fn (): array => throw $exception;
        }
    }
}
