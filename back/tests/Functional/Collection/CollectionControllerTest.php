<?php

declare(strict_types=1);
namespace App\Tests\Functional\Collection;
use App\Tests\Functional\BaseFunctionalTest;
use App\Collection\Application\Add\AddToCollectionCommand;
use App\Collection\Application\AddRemainingToWishlist\AddRemainingToWishlistCommand;
use App\Collection\Application\Get\GetCollectionQuery;
use App\Collection\Application\GetDetail\GetCollectionDetailQuery;
use App\Collection\Application\PurchaseVolume\PurchaseVolumeCommand;
use App\Collection\Application\Remove\RemoveFromCollectionCommand;
use App\Collection\Application\SyncVolumes\SyncVolumesCommand;
use App\Collection\Application\ToggleVolume\ToggleVolumeCommand;
use App\Collection\Application\UpdateStatus\UpdateReadingStatusCommand;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
class CollectionControllerTest extends BaseFunctionalTest
{
    public function testListReturns200(): void
    {
        $client = $this->createAuthenticatedClient();
        $mockQuery = $this->createMock(QueryBusInterface::class);
        $mockQuery->method('ask')
            ->with($this->isInstanceOf(GetCollectionQuery::class))
            ->willReturn([['id' => 'ce-1']]);
        static::getContainer()->set(QueryBusInterface::class, $mockQuery);
        $client->request('GET', '/api/collection');
        $this->assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertCount(1, $body);
    }
    public function testAddReturns201(): void
        $mockCommand = $this->createMock(CommandBusInterface::class);
        $mockCommand->method('dispatch')
            ->with($this->isInstanceOf(AddToCollectionCommand::class))
            ->willReturn('new-entry-id');
        static::getContainer()->set(CommandBusInterface::class, $mockCommand);
        $client->request(
            'POST',
            '/api/collection',
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['mangaId' => 'm-1']),
        );
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('new-entry-id', $body['id']);
    public function testGetDetailReturns200(): void
            ->with($this->isInstanceOf(GetCollectionDetailQuery::class))
            ->willReturn(['id' => 'ce-1', 'volumes' => []]);
        $client->request('GET', '/api/collection/ce-1');
        $this->assertSame('ce-1', $body['id']);
    public function testGetDetailReturns404WhenNotFound(): void
        $mockQuery->method('ask')->willThrowException(new NotFoundException('CollectionEntry', 'missing'));
        $client->request('GET', '/api/collection/missing');
        $this->assertResponseStatusCodeSame(404);
    public function testRemoveReturns204(): void
        $mockCommand->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(RemoveFromCollectionCommand::class));
        $client->request('DELETE', '/api/collection/ce-1');
        $this->assertResponseStatusCodeSame(204);
    public function testUpdateStatusReturns204(): void
            ->with($this->isInstanceOf(UpdateReadingStatusCommand::class));
            'PATCH',
            '/api/collection/ce-1/status',
            json_encode(['status' => 'in_progress']),
    public function testToggleVolumeReturns204(): void
            ->with($this->isInstanceOf(ToggleVolumeCommand::class));
            '/api/collection/ce-1/volumes/ve-1/toggle',
            json_encode(['field' => 'isOwned']),
    public function testAddRemainingToWishlistReturns204(): void
            ->with($this->isInstanceOf(AddRemainingToWishlistCommand::class));
        $client->request('POST', '/api/collection/ce-1/add-to-wishlist');
    public function testPurchaseVolumeReturns204(): void
            ->with($this->isInstanceOf(PurchaseVolumeCommand::class));
        $client->request('POST', '/api/collection/ce-1/volumes/ve-1/purchase');
    public function testSyncVolumesReturns204(): void
            ->with($this->isInstanceOf(SyncVolumesCommand::class));
            '/api/collection/ce-1/sync-volumes',
            json_encode(['upToVolume' => 10]),
}
