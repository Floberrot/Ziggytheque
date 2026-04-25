<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Messenger;

use App\Notification\Application\Discord\SendSchedulerDiscordSummaryMessage;
use App\Notification\Application\Fetch\FetchJikanNewsMessage;
use App\Notification\Application\Fetch\FetchRssFeedMessage;
use App\Notification\Domain\CrawlJobRepositoryInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CrawlJobCompletionListener
{
    public function __construct(
        private CrawlJobRepositoryInterface $crawlJobRepository,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[AsEventListener]
    public function onHandled(WorkerMessageHandledEvent $event): void
    {
        $this->complete($event->getEnvelope()->getMessage(), success: true);
    }

    #[AsEventListener]
    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $this->complete($event->getEnvelope()->getMessage(), success: false);
    }

    private function complete(object $message, bool $success): void
    {
        [$crawlJobId, $crawlRunId] = match (true) {
            $message instanceof FetchRssFeedMessage  => [$message->crawlJobId, $message->crawlRunId],
            $message instanceof FetchJikanNewsMessage => [$message->crawlJobId, $message->crawlRunId],
            default => [null, null],
        };

        if ($crawlJobId === null || $crawlRunId === null) {
            return;
        }

        $startedAt = $this->crawlJobRepository->completeAndTryFinishRun($crawlJobId, $crawlRunId, $success);

        if ($startedAt !== null) {
            $this->messageBus->dispatch(new SendSchedulerDiscordSummaryMessage(scheduledAt: $startedAt));
        }
    }
}
