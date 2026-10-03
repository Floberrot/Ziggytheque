<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\AutoCovers;

use App\Manga\Application\AutoCovers\AutoCoversBatchHandler;
use App\Manga\Application\AutoCovers\AutoCoversBatchMessage;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaCoverProviderInterface;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Service\CoverBatchResolver;
use App\Tests\Doubles\Manga\InMemoryCoverBatchProgressPublisher;
use PHPUnit\Framework\TestCase;

final class AutoCoversBatchHandlerTest extends TestCase
{
    private InMemoryCoverBatchProgressPublisher $publisher;

    protected function setUp(): void
    {
        $this->publisher = new InMemoryCoverBatchProgressPublisher();
    }

    private function handler(MangaRepositoryInterface $mangaRepository): AutoCoversBatchHandler
    {
        $coverProvider = $this->createStub(MangaCoverProviderInterface::class);
        $coverProvider->method('findByContext')->willReturn(null);

        return new AutoCoversBatchHandler(
            mangaRepository: $mangaRepository,
            coverBatchResolver: new CoverBatchResolver($coverProvider),
            publisher: $this->publisher,
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

    public function testHandlerDoesNothingWhenMangaNotFound(): void
    {
        $mangaRepository = $this->createMock(MangaRepositoryInterface::class);
        $mangaRepository->expects($this->once())->method('findById')->with('nonexistent-id')->willReturn(null);
        $mangaRepository->expects($this->never())->method('save');

        $message = new AutoCoversBatchMessage(
            mangaId: 'nonexistent-id',
            batchId: 'batch-1',
            force: false,
            volumeIds: null,
        );

        ($this->handler($mangaRepository))($message);

        $this->assertEmpty($this->publisher->events);
    }

    public function testHandlerPublishesBatchStartedAndCompleted(): void
    {
        $manga = $this->manga();

        $mangaRepository = $this->createMock(MangaRepositoryInterface::class);
        $mangaRepository->expects($this->once())->method('findById')->with('manga-1')->willReturn($manga);
        $mangaRepository->expects($this->once())->method('save')->with($manga);

        $message = new AutoCoversBatchMessage(
            mangaId: 'manga-1',
            batchId: 'batch-abc',
            force: false,
            volumeIds: null,
        );

        ($this->handler($mangaRepository))($message);

        $types = array_map(static fn ($event) => $event->type, $this->publisher->events);
        $this->assertSame('batch_started', $types[0]);
        $this->assertSame('batch_completed', end($types));
    }

    public function testHandlerSetsCorrectBatchIdOnAllEvents(): void
    {
        $mangaRepository = $this->createStub(MangaRepositoryInterface::class);
        $mangaRepository->method('findById')->willReturn($this->manga());

        $message = new AutoCoversBatchMessage(
            mangaId: 'manga-1',
            batchId: 'my-batch-id',
            force: false,
            volumeIds: null,
        );

        ($this->handler($mangaRepository))($message);

        $this->assertNotEmpty($this->publisher->events);
        foreach ($this->publisher->events as $event) {
            $this->assertSame('my-batch-id', $event->batchId);
        }
    }
}
