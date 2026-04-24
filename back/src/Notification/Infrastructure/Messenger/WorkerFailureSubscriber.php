<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Messenger;

use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Domain\DiscordNotifierInterface;
use App\Notification\Domain\EventTypeEnum;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Uuid;

#[AsEventListener]
final readonly class WorkerFailureSubscriber
{
    private const ERROR_THRESHOLD = 5;
    private const WINDOW_MINUTES  = 10;

    public function __construct(
        private ActivityLogRepositoryInterface $activityLogRepository,
        private DiscordNotifierInterface $discord,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $throwable   = $event->getThrowable();
        $messageName = get_class($event->getEnvelope()->getMessage());

        $log = new ActivityLog(
            id: Uuid::v4()->toRfc4122(),
            eventType: EventTypeEnum::WorkerFailure,
            sourceName: 'worker',
            metadata: [
                'message_class' => $messageName,
                'exception'     => $throwable::class,
                'will_retry'    => !$event->willRetry(),
            ],
        );
        $log->markError($throwable->getMessage());
        $this->activityLogRepository->save($log);

        if ($event->willRetry()) {
            return;
        }

        $recentErrors = $this->activityLogRepository->countRecentErrors(self::WINDOW_MINUTES);

        if ($recentErrors >= self::ERROR_THRESHOLD) {
            $this->discord->sendAlert(
                title: sprintf('%d erreurs worker en %d min', $recentErrors, self::WINDOW_MINUTES),
                description: sprintf(
                    "**Message:** `%s`\n**Erreur:** %s",
                    $messageName,
                    mb_substr($throwable->getMessage(), 0, 500),
                ),
                critical: true,
            );
        }
    }
}
