<?php

declare(strict_types=1);
namespace App\Tests\Functional\Manga;
use App\Tests\Functional\BaseFunctionalTest;
use App\Manga\Application\AddVolume\AddVolumeCommand;
use App\Manga\Application\Get\GetMangaQuery;
use App\Manga\Application\Import\ImportMangaCommand;
use App\Manga\Application\Search\SearchMangaQuery;
use App\Manga\Application\SearchExternal\SearchExternalMangaQuery;
use App\Manga\Application\Update\UpdateMangaCommand;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
class MangaControllerTest extends BaseFunctionalTest
{
    public function testSearchReturns200(): void
    {
        $client = $this->createAuthenticatedClient();
        $mockQuery = $this->createMock(QueryBusInterface::class);
        $mockQuery->method('ask')
            ->with($this->isInstanceOf(SearchMangaQuery::class))
            ->willReturn([['id' => 'm-1', 'title' => 'Naruto']]);
        static::getContainer()->set(QueryBusInterface::class, $mockQuery);
        $client->request('GET', '/api/manga?q=naruto');
        $this->assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertCount(1, $body);
    }
    public function testGetReturns200WhenFound(): void
            ->with($this->isInstanceOf(GetMangaQuery::class))
            ->willReturn(['id' => 'm-1', 'title' => 'Naruto', 'volumes' => []]);
        $client->request('GET', '/api/manga/m-1');
        $this->assertSame('m-1', $body['id']);
    public function testGetReturns404WhenNotFound(): void
        $mockQuery->method('ask')->willThrowException(new NotFoundException('Manga', 'missing'));
        $client->request('GET', '/api/manga/missing');
        $this->assertResponseStatusCodeSame(404);
    public function testImportReturns201WithId(): void
        $mockCommand = $this->createMock(CommandBusInterface::class);
        $mockCommand->method('dispatch')
            ->with($this->isInstanceOf(ImportMangaCommand::class))
            ->willReturn('new-manga-id');
        static::getContainer()->set(CommandBusInterface::class, $mockCommand);
        $client->request(
            'POST',
            '/api/manga',
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => 'Naruto', 'edition' => 'Kana', 'language' => 'fr']),
        );
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('new-manga-id', $body['id']);
    public function testUpdateReturns204(): void
        $mockCommand->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(UpdateMangaCommand::class));
            'PATCH',
            '/api/manga/m-1',
            json_encode(['title' => 'New Title']),
        $this->assertResponseStatusCodeSame(204);
    public function testAddVolumeReturns201WithId(): void
            ->with($this->isInstanceOf(AddVolumeCommand::class))
            ->willReturn('new-volume-id');
            '/api/manga/m-1/volumes',
            json_encode(['number' => 1]),
        $this->assertSame('new-volume-id', $body['id']);
    public function testSearchExternalReturns200(): void
            ->with($this->isInstanceOf(SearchExternalMangaQuery::class))
            ->willReturn([['externalId' => 'ext-1', 'title' => 'One Piece']]);
        $client->request('GET', '/api/manga/external?q=one+piece');
}
