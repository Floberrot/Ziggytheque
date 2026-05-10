<?php

declare(strict_types=1);

namespace App\Manga\Application\AutoCovers;

use App\Manga\Domain\CoverBatchSubscriberAuthorizerInterface;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class StartCoverBatchHandler
{
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

        $this->messageBus->dispatch(new AutoCoversBatchMessage(
            mangaId: $command->mangaId,
            batchId: $batchId,
            force: $command->force,
            volumeIds: $command->volumeIds,
        ));

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
