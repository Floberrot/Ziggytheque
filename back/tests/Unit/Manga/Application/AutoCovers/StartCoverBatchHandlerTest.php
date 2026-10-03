<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\AutoCovers;

use App\Manga\Application\AutoCovers\AutoCoversBatchMessage;
use App\Manga\Application\AutoCovers\StartCoverBatchCommand;
use App\Manga\Application\AutoCovers\StartCoverBatchHandler;
use App\Manga\Application\AutoCovers\StartCoverBatchResult;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use App\Tests\Doubles\Manga\StubCoverBatchSubscriberAuthorizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class StartCoverBatchHandlerTest extends TestCase
{
    private function handler(?Manga $storedManga, MessageBusInterface $messageBus): StartCoverBatchHandler
    {
        $mangaRepository = $this->createStub(MangaRepositoryInterface::class);
        $mangaRepository->method('findById')->willReturn($storedManga);

        return new StartCoverBatchHandler(
            mangaRepository: $mangaRepository,
            messageBus: $messageBus,
            subscriberAuthorizer: new StubCoverBatchSubscriberAuthorizer(),
        );
    }

    private function manga(): Manga
    {
        return new Manga(
            id: 'manga-1',
            title: 'Test Manga',
            edition: null,
            language: 'fr',
        );
    }

    /** A bus that accepts whatever is dispatched, for tests that only read the result. */
    private function acceptingBus(): MessageBusInterface
    {
        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturnCallback(static fn (object $message) => new Envelope($message));

        return $messageBus;
    }

    public function testThrowsNotFoundExceptionWhenMangaDoesNotExist(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $this->expectException(NotFoundException::class);

        ($this->handler(null, $messageBus))(new StartCoverBatchCommand(
            mangaId: 'nonexistent-id',
            force: false,
            volumeIds: null,
        ));
    }

    public function testDispatchesAsyncMessageAndReturnsBatchResult(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(AutoCoversBatchMessage::class),
                $this->callback(static function (array $stamps): bool {
                    foreach ($stamps as $stamp) {
                        if ($stamp instanceof DelayStamp && $stamp->getDelay() === 1000) {
                            return true;
                        }
                    }

                    return false;
                }),
            )
            ->willReturnCallback(static fn (object $message) => new Envelope($message));

        $result = ($this->handler($this->manga(), $messageBus))(new StartCoverBatchCommand(
            mangaId: 'manga-1',
            force: false,
            volumeIds: null,
        ));

        $this->assertInstanceOf(StartCoverBatchResult::class, $result);
        $this->assertNotEmpty($result->batchId);
        $this->assertStringContainsString($result->batchId, $result->topic);
        $this->assertStringContainsString('stub-subscriber-token', $result->subscriberToken);
        $this->assertSame('http://localhost:8000/.well-known/mercure', $result->mercureUrl);
    }

    public function testResultToArrayContainsRequiredKeys(): void
    {
        $result = ($this->handler($this->manga(), $this->acceptingBus()))(new StartCoverBatchCommand(
            mangaId: 'manga-1',
            force: false,
            volumeIds: null,
        ));

        $array = $result->toArray();
        $this->assertArrayHasKey('batchId', $array);
        $this->assertArrayHasKey('mercureUrl', $array);
        $this->assertArrayHasKey('subscriberToken', $array);
        $this->assertArrayHasKey('topic', $array);
    }
}
