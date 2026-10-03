<?php

declare(strict_types=1);

namespace App\Tests\Unit\Share\Application\GetSnapshot;

use App\Share\Application\GetSnapshot\GetShareSnapshotHandler;
use App\Share\Application\GetSnapshot\GetShareSnapshotQuery;
use App\Share\Domain\ShareSnapshot;
use App\Share\Domain\ShareSnapshotRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

final class GetShareSnapshotHandlerTest extends TestCase
{
    public function testReturnsThePublicViewOfTheSnapshot(): void
    {
        $snapshot = new ShareSnapshot(
            id: 'snapshot-1',
            token: str_repeat('ab', 16),
            owner: null,
            ownerName: 'Reader',
            payload: ['totalMangas' => 4],
        );
        $repository = $this->createMock(ShareSnapshotRepositoryInterface::class);
        $repository->expects($this->once())->method('findByToken')->with(str_repeat('ab', 16))->willReturn($snapshot);

        $view = (new GetShareSnapshotHandler($repository))(new GetShareSnapshotQuery(str_repeat('ab', 16)));

        $this->assertSame($snapshot->toPublicArray(), $view);
        $this->assertArrayNotHasKey('token', $view);
    }

    public function testAnUnknownTokenIsNotFound(): void
    {
        $repository = $this->createStub(ShareSnapshotRepositoryInterface::class);
        $repository->method('findByToken')->willReturn(null);

        $this->expectException(NotFoundException::class);

        (new GetShareSnapshotHandler($repository))(new GetShareSnapshotQuery('unknown'));
    }
}
