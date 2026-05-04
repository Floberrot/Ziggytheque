<?php

declare(strict_types=1);

namespace App\Collection\Application\GetShelf;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\VolumeEntry;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetShelfHandler
{
    public function __construct(private CollectionRepositoryInterface $repository)
    {
    }

    /** @return list<array<string, mixed>> */
    public function __invoke(GetShelfQuery $query): array
    {
        $entries = $this->repository->findAll();
        $result = [];

        foreach ($entries as $entry) {
            $ownedVolumes = $entry->volumeEntries
                ->filter(fn (VolumeEntry $volumeEntry) => $volumeEntry->isOwned)
                ->toArray();

            if (empty($ownedVolumes)) {
                continue;
            }

            usort(
                $ownedVolumes,
                static fn (VolumeEntry $a, VolumeEntry $b) => $a->volume->number <=> $b->volume->number,
            );

            $result[] = [
                'id'     => $entry->id,
                'manga'  => [
                    'id'       => $entry->manga->id,
                    'title'    => $entry->manga->title,
                    'edition'  => $entry->manga->edition,
                    'coverUrl' => $entry->manga->coverUrl,
                ],
                'volumes' => array_map(
                    static fn (VolumeEntry $volumeEntry) => [
                        'id'       => $volumeEntry->id,
                        'number'   => $volumeEntry->volume->number,
                        'coverUrl' => $volumeEntry->volume->coverUrl,
                    ],
                    $ownedVolumes,
                ),
            ];
        }

        return $result;
    }
}
