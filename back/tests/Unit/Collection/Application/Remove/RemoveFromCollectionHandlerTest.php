<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\Remove;

use App\Collection\Application\Remove\RemoveFromCollectionCommand;
use App\Collection\Application\Remove\RemoveFromCollectionHandler;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Manga\Domain\Manga;
use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class RemoveFromCollectionHandlerTest extends TestCase
{
    public function testDeletesExistingEntry(): void
    {
        $manga = new Manga('m-1', 'Test', 'Ed', 'fr');
        $entry = new CollectionEntry('ce-1', $manga);

        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn($entry);
        $repository->expects($this->once())->method('delete')->with($entry);

        $handler = new RemoveFromCollectionHandler($repository);
        $handler(new RemoveFromCollectionCommand('ce-1'));
    }

    public function testThrowsWhenNotFound(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $handler = new RemoveFromCollectionHandler($repository);

        $this->expectException(NotFoundException::class);
        $handler(new RemoveFromCollectionCommand('missing'));
    }
}
