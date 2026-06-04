<?php

declare(strict_types=1);

namespace App\Manga\Application\SearchVolumeExternal;

use App\Manga\Domain\MangaVolumeCoverDto;
use App\Manga\Domain\MultiContextCoverProviderInterface;
use App\Manga\Domain\MultiSourceCoverProviderInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class SearchVolumeExternalHandler
{
    public function __construct(
        private MultiSourceCoverProviderInterface $multiSourceProvider,
        private ContainerInterface $providerLocator,
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function __invoke(SearchVolumeExternalQuery $query): array
    {
        $volumeNumber = $query->volumeNumber ?? 1;
        $covers = $this->resolveCovers($query, $volumeNumber);

        return array_map(
            fn (MangaVolumeCoverDto $coverDto) => $this->mapDtoToArray($coverDto, $query->search, $volumeNumber),
            $covers,
        );
    }

    /**
     * The default "composite" provider merges every context source; a specific
     * provider key narrows the search to that single source.
     *
     * @return list<MangaVolumeCoverDto>
     */
    private function resolveCovers(SearchVolumeExternalQuery $query, int $volumeNumber): array
    {
        if ($query->provider !== 'composite' && $this->providerLocator->has($query->provider)) {
            /** @var MultiContextCoverProviderInterface $provider */
            $provider = $this->providerLocator->get($query->provider);

            return $provider->findAllByContext(
                mangaTitle: $query->search,
                edition: $query->edition,
                volumeNumber: $volumeNumber,
            );
        }

        return $this->multiSourceProvider->findAllByContext(
            mangaTitle: $query->search,
            edition: $query->edition,
            volumeNumber: $volumeNumber,
        );
    }

    /** @return array<string, mixed> */
    private function mapDtoToArray(MangaVolumeCoverDto $dto, string $title, int $volumeNumber): array
    {
        return [
            'externalId' => null,
            'title' => $title,
            'edition' => null,
            'coverUrl' => $dto->coverUrl,
            'spineUrl' => $dto->spineUrl,
            'isbn' => $dto->isbn?->value,
            'language' => 'fr',
            'totalVolumes' => null,
            'source' => $dto->source,
        ];
    }
}
