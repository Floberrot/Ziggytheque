<?php

declare(strict_types=1);

namespace App\Manga\Application\Scan;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\ScanResultPublisherInterface;
use App\Manga\Domain\ScanTokenIssuerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SubmitScanHandler
{
    public function __construct(
        private ScanTokenIssuerInterface $scanTokenIssuer,
        private ScanResultPublisherInterface $scanResultPublisher,
    ) {
    }

    public function __invoke(SubmitScanCommand $command): void
    {
        $sessionId = $this->scanTokenIssuer->verify($command->scanToken);
        $isbn = Isbn::fromString($command->isbn);
        $this->scanResultPublisher->publish($sessionId, $isbn->value);
    }
}
