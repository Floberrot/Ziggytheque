<?php

declare(strict_types=1);

namespace App\Manga\Application\Scan;

use App\Manga\Domain\CoverBatchSubscriberAuthorizerInterface;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\ScanTokenIssuerInterface;
use App\Manga\Domain\Volume;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateScanSessionHandler
{
    public function __construct(
        private MangaRepositoryInterface $mangaRepository,
        private CoverBatchSubscriberAuthorizerInterface $subscriberAuthorizer,
        private ScanTokenIssuerInterface $scanTokenIssuer,
    ) {
    }

    public function __invoke(CreateScanSessionCommand $command): ScanSessionResult
    {
        $manga = $this->mangaRepository->findById($command->mangaId);

        if ($manga === null) {
            throw new NotFoundException('Manga', $command->mangaId);
        }

        $volume = $manga->volumes
            ->filter(fn (Volume $volume) => $volume->id === $command->volumeId)
            ->first();

        if ($volume === false) {
            throw new NotFoundException('Volume', $command->volumeId);
        }

        $sessionId = Uuid::v4()->toRfc4122();

        $subscriberToken = $this->subscriberAuthorizer->issueToken($sessionId, ttlSeconds: 600);
        $topic = $this->subscriberAuthorizer->topicFor($sessionId);
        $mercureUrl = $this->subscriberAuthorizer->publicHubUrl();
        $scanToken = $this->scanTokenIssuer->issue($sessionId, ttlSeconds: 600);

        return new ScanSessionResult(
            sessionId: $sessionId,
            scanToken: $scanToken,
            mercureUrl: $mercureUrl,
            subscriberToken: $subscriberToken,
            topic: $topic,
        );
    }
}
