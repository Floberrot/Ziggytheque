<?php

declare(strict_types=1);

namespace App\Manga\Application\AddVolume;

use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Volume;
use App\PriceCode\Domain\PriceCodeRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AddVolumeHandler
{
    public function __construct(
        private MangaRepositoryInterface $mangaRepository,
        private PriceCodeRepositoryInterface $priceCodeRepository,
    ) {
    }

    public function __invoke(AddVolumeCommand $command): string
    {
        $manga = $this->mangaRepository->findById($command->mangaId);

        if ($manga === null) {
            throw new NotFoundException('Manga', $command->mangaId);
        }

        $priceCode = null;
        if ($command->priceCode !== null) {
            $priceCode = $this->priceCodeRepository->findByCode($command->priceCode);
        }

        $volume = new Volume(
            id: Uuid::v4()->toRfc4122(),
            manga: $manga,
            number: $command->number,
            coverUrl: $command->coverUrl,
            priceCode: $priceCode,
            releaseDate: $command->releaseDate !== null
                ? new \DateTimeImmutable($command->releaseDate)
                : null,
        );

        $manga->addVolume($volume);
        $this->mangaRepository->save($manga);

        return $volume->id;
    }
}
