<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\Scan;

use App\Manga\Application\Scan\CreateScanSessionCommand;
use App\Manga\Application\Scan\CreateScanSessionHandler;
use App\Manga\Domain\CoverBatchSubscriberAuthorizerInterface;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\ScanTokenIssuerInterface;
use App\Manga\Domain\Volume;
use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

final class CreateScanSessionHandlerTest extends TestCase
{
    /** @var list<int> */
    private array $issuedTtls = [];

    private function handler(?Manga $manga = null): CreateScanSessionHandler
    {
        $repository = $this->createStub(MangaRepositoryInterface::class);
        $repository->method('findById')->willReturn($manga);

        $authorizer = $this->createStub(CoverBatchSubscriberAuthorizerInterface::class);
        $authorizer->method('issueToken')->willReturnCallback(function (string $sessionId, int $ttlSeconds): string {
            $this->issuedTtls[] = $ttlSeconds;

            return 'subscriber-token';
        });
        $authorizer->method('topicFor')->willReturnCallback(static fn (string $sessionId): string => 'scan/' . $sessionId);
        $authorizer->method('publicHubUrl')->willReturn('https://hub.example/.well-known/mercure');

        $issuer = $this->createStub(ScanTokenIssuerInterface::class);
        $issuer->method('issue')->willReturnCallback(function (string $sessionId, int $ttlSeconds): string {
            $this->issuedTtls[] = $ttlSeconds;

            return 'scan-token';
        });

        return new CreateScanSessionHandler($repository, $authorizer, $issuer);
    }

    public function testAShelfSessionNeedsNoVolumeAndLastsThirtyMinutes(): void
    {
        $result = ($this->handler())(new CreateScanSessionCommand());

        $this->assertSame('scan-token', $result->scanToken);
        $this->assertSame([1800, 1800], $this->issuedTtls);
    }

    public function testAVolumeSessionChecksTheVolumeAndLastsTenMinutes(): void
    {
        $manga = new Manga(id: 'm1', title: 'Berserk', edition: null, language: 'fr');
        $manga->addVolume(new Volume(id: 'v1', manga: $manga, number: 1));

        ($this->handler($manga))(new CreateScanSessionCommand(mangaId: 'm1', volumeId: 'v1'));

        $this->assertSame([600, 600], $this->issuedTtls);
    }

    public function testAnUnknownVolumeIsNotFound(): void
    {
        $manga = new Manga(id: 'm1', title: 'Berserk', edition: null, language: 'fr');

        $this->expectException(NotFoundException::class);

        ($this->handler($manga))(new CreateScanSessionCommand(mangaId: 'm1', volumeId: 'missing'));
    }

    public function testAnUnknownMangaIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        ($this->handler())(new CreateScanSessionCommand(volumeId: 'v1'));
    }
}
