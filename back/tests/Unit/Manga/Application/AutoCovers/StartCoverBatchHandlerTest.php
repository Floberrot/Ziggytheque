<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\AutoCovers;

use App\Manga\Application\AutoCovers\StartCoverBatchCommand;
use App\Manga\Application\AutoCovers\StartCoverBatchHandler;
use App\Manga\Application\AutoCovers\StartCoverBatchResult;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use App\Tests\Doubles\Manga\StubCoverBatchSubscriberAuthorizer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class StartCoverBatchHandlerTest extends TestCase
{
    private MangaRepositoryInterface&MockObject $mangaRepository;
    private MessageBusInterface&MockObject $messageBus;
    private StartCoverBatchHandler $handler;

    protected function setUp(): void
    {
        $this->mangaRepository = $this->createMock(MangaRepositoryInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $authorizer = new StubCoverBatchSubscriberAuthorizer();

        $this->handler = new StartCoverBatchHandler(
            mangaRepository: $this->mangaRepository,
            messageBus: $this->messageBus,
            subscriberAuthorizer: $authorizer,
        );
    }

    public function testThrowsNotFoundExceptionWhenMangaDoesNotExist(): void
    {
        $this->mangaRepository->method('findById')->willReturn(null);
        $this->messageBus->expects($this->never())->method('dispatch');

        $this->expectException(NotFoundException::class);

        ($this->handler)(new StartCoverBatchCommand(
            mangaId: 'nonexistent-id',
            force: false,
            volumeIds: null,
        ));
    }

    public function testDispatchesAsyncMessageAndReturnsBatchResult(): void
    {
        $manga = new Manga(
            id: 'manga-1',
            title: 'Test Manga',
            edition: null,
            language: 'fr',
        );

        $this->mangaRepository->method('findById')->willReturn($manga);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message) => new Envelope($message));

        $result = ($this->handler)(new StartCoverBatchCommand(
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
        $manga = new Manga(
            id: 'manga-1',
            title: 'Test Manga',
            edition: null,
            language: 'fr',
        );

        $this->mangaRepository->method('findById')->willReturn($manga);
        $this->messageBus
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message) => new Envelope($message));

        $result = ($this->handler)(new StartCoverBatchCommand(
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
