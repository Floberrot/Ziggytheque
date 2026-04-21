<?php

declare(strict_types=1);

namespace App\Manga\Application\GetVolumeCovers;

use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalVolumeDto;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetVolumeCoversHandler
{
    public function __construct(private ExternalApiClientInterface $client)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function __invoke(GetVolumeCoversQuery $query): array
    {
        return array_map(
            static fn (ExternalVolumeDto $dto) => [
                'number'      => $dto->number,
                'coverUrl'    => $dto->coverUrl,
                'releaseDate' => $dto->releaseDate?->format('Y-m-d'),
            ],
            $this->client->getVolumeCovers($query->externalId),
        );
    }
}
