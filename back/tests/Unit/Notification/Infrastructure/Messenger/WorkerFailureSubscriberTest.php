<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Infrastructure\Messenger;

use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Domain\DiscordNotifierInterface;
use App\Notification\Domain\Service\RssFeedParserException;
use App\Notification\Infrastructure\Messenger\WorkerFailureSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

final class WorkerFailureSubscriberTest extends TestCase
{
    private ActivityLogRepositoryInterface&MockObject $activityLogRepository;
    private DiscordNotifierInterface&MockObject $discord;
    private WorkerFailureSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->activityLogRepository = $this->createMock(ActivityLogRepositoryInterface::class);
        $this->discord               = $this->createMock(DiscordNotifierInterface::class);
        $this->subscriber            = new WorkerFailureSubscriber(
            $this->activityLogRepository,
            $this->discord,
        );
    }

    public function testAppBugAlertsWhenThresholdReached(): void
    {
        $event = $this->makeEvent(new RuntimeException('boom in our code'));

        $this->expectLogSavedWithExternalFlag(false);
        $this->activityLogRepository->method('countRecentErrors')->willReturn(5);

        $this->discord->expects($this->once())->method('sendAlert');

        ($this->subscriber)($event);
    }

    public function testAppBugDoesNotAlertBelowThreshold(): void
    {
        $event = $this->makeEvent(new RuntimeException('boom in our code'));

        $this->expectLogSavedWithExternalFlag(false);
        $this->activityLogRepository->method('countRecentErrors')->willReturn(4);

        $this->discord->expects($this->never())->method('sendAlert');

        ($this->subscriber)($event);
    }

    public function testExternalServerExceptionIsLoggedButNeverAlerts(): void
    {
        $event = $this->makeEvent($this->makeServerException());

        $this->expectLogSavedWithExternalFlag(true);
        $this->activityLogRepository->expects($this->never())->method('countRecentErrors');
        $this->discord->expects($this->never())->method('sendAlert');

        ($this->subscriber)($event);
    }

    public function testExternalTransportExceptionIsLoggedButNeverAlerts(): void
    {
        $event = $this->makeEvent($this->makeTransportException());

        $this->expectLogSavedWithExternalFlag(true);
        $this->activityLogRepository->expects($this->never())->method('countRecentErrors');
        $this->discord->expects($this->never())->method('sendAlert');

        ($this->subscriber)($event);
    }

    public function testRssFeedParserExceptionIsConsideredExternal(): void
    {
        $event = $this->makeEvent(RssFeedParserException::httpError(503));

        $this->expectLogSavedWithExternalFlag(true);
        $this->activityLogRepository->expects($this->never())->method('countRecentErrors');
        $this->discord->expects($this->never())->method('sendAlert');

        ($this->subscriber)($event);
    }

    public function testWrappedExternalExceptionIsStillDetected(): void
    {
        $wrapper = new RuntimeException('handler failed', 0, $this->makeServerException());
        $event   = $this->makeEvent($wrapper);

        $this->expectLogSavedWithExternalFlag(true);
        $this->activityLogRepository->expects($this->never())->method('countRecentErrors');
        $this->discord->expects($this->never())->method('sendAlert');

        ($this->subscriber)($event);
    }

    public function testRetryingFailureSkipsAlerting(): void
    {
        $event = $this->makeEvent(new RuntimeException('boom'), willRetry: true);

        $this->activityLogRepository->expects($this->once())->method('save');
        $this->activityLogRepository->expects($this->never())->method('countRecentErrors');
        $this->discord->expects($this->never())->method('sendAlert');

        ($this->subscriber)($event);
    }

    private function makeEvent(Throwable $throwable, bool $willRetry = false): WorkerMessageFailedEvent
    {
        $envelope = Envelope::wrap(new \stdClass());
        $event    = new WorkerMessageFailedEvent($envelope, 'test-receiver', $throwable);
        if ($willRetry) {
            $event->setForRetry();
        }

        return $event;
    }

    private function expectLogSavedWithExternalFlag(bool $external): void
    {
        $this->activityLogRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (ActivityLog $log) use ($external): bool {
                $this->assertSame('error', $log->status);
                $flag = $log->metadata['external_api_failure'] ?? false;
                $this->assertSame($external, $flag);
                return true;
            }));
    }

    private function makeServerException(): ServerExceptionInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        return new class ($response) extends RuntimeException implements ServerExceptionInterface {
            public function __construct(private readonly ResponseInterface $response)
            {
                parent::__construct('HTTP 500 from upstream');
            }

            public function getResponse(): ResponseInterface
            {
                return $this->response;
            }
        };
    }

    private function makeTransportException(): TransportExceptionInterface
    {
        return new class extends RuntimeException implements TransportExceptionInterface {
            public function __construct()
            {
                parent::__construct('connection refused');
            }
        };
    }
}
