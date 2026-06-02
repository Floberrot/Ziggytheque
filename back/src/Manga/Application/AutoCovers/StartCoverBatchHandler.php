<?php

declare(strict_types=1);

namespace App\Manga\Application\AutoCovers;

use App\Manga\Domain\CoverBatchSubscriberAuthorizerInterface;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class StartCoverBatchHandler
{
    /**
     * Give the SPA's EventSource time to subscribe to the private Mercure topic
     * before the worker starts publishing — Mercure drops events sent to a topic
     * that has no subscriber yet, so a too-eager worker would race the browser.
     */
    private const int SUBSCRIBE_GRACE_MS = 1000;

    public function __construct(
        private MangaRepositoryInterface $mangaRepository,
        private MessageBusInterface $messageBus,
        private CoverBatchSubscriberAuthorizerInterface $subscriberAuthorizer,
    ) {
    }

    public function __invoke(StartCoverBatchCommand $command): StartCoverBatchResult
    {
        $manga = $this->mangaRepository->findById($command->mangaId);

        if ($manga === null) {
            throw new NotFoundException('Manga', $command->mangaId);
        }

        $batchId = Uuid::v4()->toRfc4122();

        $this->messageBus->dispatch(
            new AutoCoversBatchMessage(
                mangaId: $command->mangaId,
                batchId: $batchId,
                force: $command->force,
                volumeIds: $command->volumeIds,
            ),
            [new DelayStamp(self::SUBSCRIBE_GRACE_MS)],
        );

        $subscriberToken = $this->subscriberAuthorizer->issueToken($batchId, ttlSeconds: 600);
        $topic = $this->subscriberAuthorizer->topicFor($batchId);
        $mercureUrl = $this->subscriberAuthorizer->publicHubUrl();

        return new StartCoverBatchResult(
            batchId: $batchId,
            mercureUrl: $mercureUrl,
            subscriberToken: $subscriberToken,
            topic: $topic,
        );
    }
}
