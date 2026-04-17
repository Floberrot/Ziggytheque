<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\UpdateStatus;

use App\Collection\Application\UpdateStatus\UpdateReadingStatusCommand;
use App\Collection\Application\UpdateStatus\UpdateReadingStatusHandler;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\ReadingStatusEnum;
use App\Manga\Domain\Manga;
use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class UpdateReadingStatusHandlerTest extends TestCase
{
    public function testUpdatesReadingStatus(): void
    {
        $manga = new Manga('m-1', 'Test', 'Ed', 'fr');
        $entry = new CollectionEntry('ce-1', $manga);

        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn($entry);
        $repository->expects($this->once())->method('save');

        $handler = new UpdateReadingStatusHandler($repository);
        $handler(new UpdateReadingStatusCommand('ce-1', 'in_progress'));

        $this->assertSame(ReadingStatusEnum::InProgress, $entry->readingStatus);
    }

    public function testThrowsWhenEntryNotFound(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $handler = new UpdateReadingStatusHandler($repository);

        $this->expectException(NotFoundException::class);
        $handler(new UpdateReadingStatusCommand('missing', 'completed'));
    }
}
