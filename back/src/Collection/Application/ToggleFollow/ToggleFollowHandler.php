<?php

declare(strict_types=1);

namespace App\Collection\Application\ToggleFollow;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ToggleFollowHandler
{
    public function __construct(private CollectionRepositoryInterface $repository) {}

    public function __invoke(ToggleFollowCommand $command): bool
    {
        $entry = $this->repository->findById($command->collectionEntryId);
        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $command->collectionEntryId);
        }

        $entry->notificationsEnabled = !$entry->notificationsEnabled;
        $this->repository->save($entry);

        return $entry->notificationsEnabled;
    }
}
