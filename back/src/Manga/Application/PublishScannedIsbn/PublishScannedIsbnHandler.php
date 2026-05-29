<?php

declare(strict_types=1);

namespace App\Manga\Application\PublishScannedIsbn;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\ScanSessionPublisherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class PublishScannedIsbnHandler
{
    public function __construct(private ScanSessionPublisherInterface $scanSessionPublisher)
    {
    }

    public function __invoke(PublishScannedIsbnCommand $command): void
    {
        $isbn = Isbn::fromString($command->isbn);
        $this->scanSessionPublisher->publishIsbn($command->sessionId, $isbn->value);
    }
}
