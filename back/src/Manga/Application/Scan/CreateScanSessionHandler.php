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
    /** One tome to enrich: a quick hand-off. */
    private const int VOLUME_SESSION_TTL = 600;

    /** A whole shelf scanned from the phone: long enough to go through it. */
    private const int SHELF_SESSION_TTL = 1800;

    public function __construct(
        private MangaRepositoryInterface $mangaRepository,
        private CoverBatchSubscriberAuthorizerInterface $subscriberAuthorizer,
        private ScanTokenIssuerInterface $scanTokenIssuer,
    ) {
    }

    public function __invoke(CreateScanSessionCommand $command): ScanSessionResult
    {
        if ($command->mangaId !== null || $command->volumeId !== null) {
            $this->assertVolumeExists($command->mangaId ?? '', $command->volumeId ?? '');
        }

        $sessionId = Uuid::v4()->toRfc4122();
        $ttlSeconds = $command->mangaId === null ? self::SHELF_SESSION_TTL : self::VOLUME_SESSION_TTL;

        $subscriberToken = $this->subscriberAuthorizer->issueToken($sessionId, ttlSeconds: $ttlSeconds);
        $topic = $this->subscriberAuthorizer->topicFor($sessionId);
        $mercureUrl = $this->subscriberAuthorizer->publicHubUrl();
        $scanToken = $this->scanTokenIssuer->issue($sessionId, ttlSeconds: $ttlSeconds);

        return new ScanSessionResult(
            sessionId: $sessionId,
            scanToken: $scanToken,
            mercureUrl: $mercureUrl,
            subscriberToken: $subscriberToken,
            topic: $topic,
        );
    }

    private function assertVolumeExists(string $mangaId, string $volumeId): void
    {
        $manga = $this->mangaRepository->findById($mangaId);
        if ($manga === null) {
            throw new NotFoundException('Manga', $mangaId);
        }

        $volume = $manga->volumes
            ->filter(fn (Volume $volume) => $volume->id === $volumeId)
            ->first();
        if ($volume === false) {
            throw new NotFoundException('Volume', $volumeId);
        }
    }
}
