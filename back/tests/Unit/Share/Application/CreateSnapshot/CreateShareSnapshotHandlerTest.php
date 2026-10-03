<?php

declare(strict_types=1);

namespace App\Tests\Unit\Share\Application\CreateSnapshot;

use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use App\Share\Application\CreateSnapshot\CreateShareSnapshotCommand;
use App\Share\Application\CreateSnapshot\CreateShareSnapshotHandler;
use App\Share\Domain\ShareSnapshot;
use App\Share\Domain\ShareSnapshotRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Security\CurrentUserProviderInterface;
use App\Stats\Domain\StatsRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class CreateShareSnapshotHandlerTest extends TestCase
{
    /** @return array<string, mixed> */
    private function stats(): array
    {
        return [
            'totalMangas'    => 4,
            'totalOwned'     => 30,
            'totalRead'      => 12,
            'totalWishlist'  => 5,
            'genreBreakdown' => ['seinen' => 3, 'shonen' => 1],
            'ownedValue'     => 245.5,
            'recentAdditions' => [['coverUrl' => 'https://covers.example/1.jpg']],
        ];
    }

    private function handler(?User $owner, ShareSnapshotRepositoryInterface $snapshotRepository): CreateShareSnapshotHandler
    {
        $statsRepository = $this->createStub(StatsRepositoryInterface::class);
        $statsRepository->method('getStats')->willReturn($this->stats());
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($owner);
        $currentUserProvider = $this->createStub(CurrentUserProviderInterface::class);
        $currentUserProvider->method('currentUserId')->willReturn('reader-1');

        return new CreateShareSnapshotHandler($snapshotRepository, $statsRepository, $userRepository, $currentUserProvider);
    }

    /** Money and covers never leave through a public link: only counts and genres are frozen. */
    public function testFreezesOnlyThePublicStatsUnderARandomToken(): void
    {
        $owner = new User(id: 'reader-1', email: 'reader@example.com', passwordHash: 'hash', displayName: 'Reader');

        $savedSnapshots     = [];
        $snapshotRepository = $this->createMock(ShareSnapshotRepositoryInterface::class);
        $snapshotRepository->expects($this->once())->method('save')->willReturnCallback(
            static function (ShareSnapshot $snapshot) use (&$savedSnapshots): void {
                $savedSnapshots[] = $snapshot;
            },
        );

        $token = ($this->handler($owner, $snapshotRepository))(new CreateShareSnapshotCommand());

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $token);
        $this->assertCount(1, $savedSnapshots);
        $this->assertSame($token, $savedSnapshots[0]->token);
        $this->assertSame($owner, $savedSnapshots[0]->owner);
        $this->assertSame('Reader', $savedSnapshots[0]->ownerName);
        $this->assertSame(
            ['totalMangas' => 4, 'totalOwned' => 30, 'totalRead' => 12, 'totalWishlist' => 5, 'genreBreakdown' => ['seinen' => 3, 'shonen' => 1]],
            $savedSnapshots[0]->payload,
        );
    }

    public function testTwoSnapshotsNeverShareAToken(): void
    {
        $owner              = new User(id: 'reader-1', email: 'reader@example.com', passwordHash: 'hash', displayName: 'Reader');
        $snapshotRepository = $this->createStub(ShareSnapshotRepositoryInterface::class);
        $handler            = $this->handler($owner, $snapshotRepository);

        $this->assertNotSame($handler(new CreateShareSnapshotCommand()), $handler(new CreateShareSnapshotCommand()));
    }

    public function testAVanishedAccountIsNotFound(): void
    {
        $snapshotRepository = $this->createMock(ShareSnapshotRepositoryInterface::class);
        $snapshotRepository->expects($this->never())->method('save');

        $this->expectException(NotFoundException::class);

        ($this->handler(null, $snapshotRepository))(new CreateShareSnapshotCommand());
    }
}
