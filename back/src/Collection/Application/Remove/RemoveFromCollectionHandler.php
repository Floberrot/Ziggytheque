<?php

declare(strict_types=1);

namespace App\Collection\Application\Remove;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RemoveFromCollectionHandler
{
    public function __construct(private CollectionRepositoryInterface $repository)
    {
    }

    public function __invoke(RemoveFromCollectionCommand $command): void
    {
        $entry = $this->repository->findById($command->id);

        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $command->id);
        }

        $this->repository->delete($entry);
    }
}
