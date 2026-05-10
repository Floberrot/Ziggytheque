<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\AutoCovers;

use App\Manga\Application\AutoCovers\AutoCoversBatchHandler;
use App\Manga\Application\AutoCovers\AutoCoversBatchMessage;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaCoverProviderInterface;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\MangaVolumeCoverDto;
use App\Manga\Domain\Service\CoverBatchResolver;
use App\Tests\Doubles\Manga\InMemoryCoverBatchProgressPublisher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AutoCoversBatchHandlerTest extends TestCase
{
    private MangaRepositoryInterface&MockObject $mangaRepository;
    private InMemoryCoverBatchProgressPublisher $publisher;
    private AutoCoversBatchHandler $handler;

    protected function setUp(): void
    {
        $this->mangaRepository = $this->createMock(MangaRepositoryInterface::class);
        $this->publisher = new InMemoryCoverBatchProgressPublisher();
        $coverProvider = $this->createMock(MangaCoverProviderInterface::class);
        $coverProvider->method('findByContext')->willReturn(null);
        $coverBatchResolver = new CoverBatchResolver($coverProvider);

        $this->handler = new AutoCoversBatchHandler(
            mangaRepository: $this->mangaRepository,
            coverBatchResolver: $coverBatchResolver,
            publisher: $this->publisher,
        );
    }

    public function testHandlerDoesNothingWhenMangaNotFound(): void
    {
        $this->mangaRepository->method('findById')->willReturn(null);

        $message = new AutoCoversBatchMessage(
            mangaId: 'nonexistent-id',
            batchId: 'batch-1',
            force: false,
            volumeIds: null,
        );

        ($this->handler)($message);

        $this->assertEmpty($this->publisher->events);
    }

    public function testHandlerPublishesBatchStartedAndCompleted(): void
    {
        $manga = new Manga(
            id: 'manga-1',
            title: 'Test Manga',
            edition: null,
            language: 'fr',
        );

        $this->mangaRepository->method('findById')->willReturn($manga);
        $this->mangaRepository->expects($this->once())->method('save')->with($manga);

        $message = new AutoCoversBatchMessage(
            mangaId: 'manga-1',
            batchId: 'batch-abc',
            force: false,
            volumeIds: null,
        );

        ($this->handler)($message);

        $types = array_map(static fn ($event) => $event->type, $this->publisher->events);
        $this->assertSame('batch_started', $types[0]);
        $this->assertSame('batch_completed', end($types));
    }

    public function testHandlerSetsCorrectBatchIdOnAllEvents(): void
    {
        $manga = new Manga(
            id: 'manga-1',
            title: 'Test Manga',
            edition: null,
            language: 'fr',
        );

        $this->mangaRepository->method('findById')->willReturn($manga);
        $this->mangaRepository->method('save');

        $message = new AutoCoversBatchMessage(
            mangaId: 'manga-1',
            batchId: 'my-batch-id',
            force: false,
            volumeIds: null,
        );

        ($this->handler)($message);

        foreach ($this->publisher->events as $event) {
            $this->assertSame('my-batch-id', $event->batchId);
        }
    }
}
