<?php

declare(strict_types=1);
namespace App\Tests\Functional\Wishlist;
use App\Tests\Functional\BaseFunctionalTest;
use App\Collection\Application\AddRemainingToWishlist\AddRemainingToWishlistCommand;
use App\Collection\Application\ClearWishlist\ClearWishlistCommand;
use App\Collection\Application\GetWishlist\GetWishlistQuery;
use App\Collection\Application\PurchaseVolume\PurchaseVolumeCommand;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
class WishlistControllerTest extends BaseFunctionalTest
{
    public function testListReturns200(): void
    {
        $client = $this->createAuthenticatedClient();
        $mockQuery = $this->createMock(QueryBusInterface::class);
        $mockQuery->method('ask')
            ->with($this->isInstanceOf(GetWishlistQuery::class))
            ->willReturn([['id' => 'ce-1', 'volumes' => []]]);
        static::getContainer()->set(QueryBusInterface::class, $mockQuery);
        $client->request('GET', '/api/wishlist');
        $this->assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertCount(1, $body);
    }
    public function testAddRemainingReturns204(): void
        $mockCommand = $this->createMock(CommandBusInterface::class);
        $mockCommand->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(AddRemainingToWishlistCommand::class));
        static::getContainer()->set(CommandBusInterface::class, $mockCommand);
        $client->request('POST', '/api/wishlist/ce-1/add-remaining');
        $this->assertResponseStatusCodeSame(204);
    public function testClearReturns204(): void
            ->with($this->isInstanceOf(ClearWishlistCommand::class));
        $client->request('DELETE', '/api/wishlist/ce-1');
    public function testPurchaseVolumeReturns204(): void
            ->with($this->isInstanceOf(PurchaseVolumeCommand::class));
        $client->request('POST', '/api/wishlist/ce-1/volumes/ve-1/purchase');
}
