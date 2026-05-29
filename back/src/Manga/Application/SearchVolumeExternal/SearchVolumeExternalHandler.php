<?php

declare(strict_types=1);

namespace App\Manga\Application\SearchVolumeExternal;

use App\Manga\Domain\EditionContext;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaCoverProviderInterface;
use App\Manga\Domain\MangaVolumeCoverDto;
use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class SearchVolumeExternalHandler
{
    public function __construct(
        private MangaCoverProviderInterface $coverProvider,
        private ContainerInterface $providerLocator,
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function __invoke(SearchVolumeExternalQuery $query): array
    {
        $provider = $this->resolveProvider($query->provider);
        $volumeNumber = $query->volumeNumber ?? 1;

        if ($query->isbn !== null) {
            $isbn = Isbn::tryFrom($query->isbn);
            if ($isbn !== null) {
                $coverDto = $provider->findByIsbn($isbn);
                if ($coverDto !== null) {
                    return [$this->mapDtoToArray($coverDto, $query->search, $volumeNumber, $query->language)];
                }
            }
        }

        $editionContext = new EditionContext(
            mangaTitle: $query->search,
            publisher: $query->publisher,
            editionLabel: $query->edition,
            year: $query->year,
            language: $query->language,
            externalWorkId: $query->externalWorkId,
        );

        $coverDto = $provider->findByContext($editionContext, $volumeNumber);

        if ($coverDto === null) {
            return [];
        }

        return [$this->mapDtoToArray($coverDto, $query->search, $volumeNumber, $query->language)];
    }

    private function resolveProvider(string $key): MangaCoverProviderInterface
    {
        if ($key !== 'composite' && $this->providerLocator->has($key)) {
            return $this->providerLocator->get($key);
        }

        return $this->coverProvider;
    }

    /** @return array<string, mixed> */
    private function mapDtoToArray(MangaVolumeCoverDto $dto, string $title, int $volumeNumber, string $language): array
    {
        return [
            'externalId'   => null,
            'title'        => $title,
            'edition'      => null,
            'coverUrl'     => $dto->coverUrl,
            'spineUrl'     => $dto->spineUrl,
            'isbn'         => $dto->isbn?->value,
            'language'     => $language,
            'totalVolumes' => null,
            'source'       => $dto->source,
        ];
    }
}
