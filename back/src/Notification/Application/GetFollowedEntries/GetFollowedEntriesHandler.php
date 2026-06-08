<?php

declare(strict_types=1);

namespace App\Notification\Application\GetFollowedEntries;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetFollowedEntriesHandler
{
    public function __construct(private CollectionRepositoryInterface $repository)
    {
    }

    /**
     * Every followed entry (notificationsEnabled = true) for the current owner,
     * sorted by title. This is the source of truth for the Actualités filter, so
     * it is never truncated by collection pagination — it scales to any number of
     * followed works.
     *
     * @return list<array{id: string, manga: array{id: string, title: string, coverUrl: string|null}}>
     */
    public function __invoke(GetFollowedEntriesQuery $query): array
    {
        $entries = $this->repository->findFollowed();

        usort(
            $entries,
            static fn (CollectionEntry $first, CollectionEntry $second): int
                => strcasecmp($first->manga->title, $second->manga->title),
        );

        return array_map(
            static fn (CollectionEntry $entry): array => [
                'id'    => $entry->id,
                'manga' => [
                    'id'       => $entry->manga->id,
                    'title'    => $entry->manga->title,
                    'coverUrl' => $entry->manga->coverUrl,
                ],
            ],
            $entries,
        );
    }
}
