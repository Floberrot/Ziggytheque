<?php

declare(strict_types=1);

namespace App\Tests\Unit\Share\Domain;

use App\Auth\Domain\User;
use App\Share\Domain\ShareSnapshot;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;

final class ShareSnapshotTest extends TestCase
{
    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'totalMangas'    => 12,
            'totalOwned'     => 80,
            'totalRead'      => 40,
            'totalWishlist'  => 5,
            'genreBreakdown' => ['shonen' => 8, 'seinen' => 4],
        ];
    }

    public function testConstructionExposesPublicFields(): void
    {
        $snapshot = new ShareSnapshot(
            id: 's1',
            token: 'abc123',
            owner: null,
            ownerName: 'Florian',
            payload: $this->payload(),
        );

        $this->assertSame('s1', $snapshot->id);
        $this->assertSame('abc123', $snapshot->token);
        $this->assertNull($snapshot->owner);
        $this->assertSame('Florian', $snapshot->ownerName);
        $this->assertSame($this->payload(), $snapshot->payload);
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $snapshot = new ShareSnapshot('s1', 'tok', null, 'Florian', $this->payload());

        $this->assertEqualsWithDelta(
            (new DateTimeImmutable())->getTimestamp(),
            $snapshot->createdAt->getTimestamp(),
            5,
        );
    }

    public function testToPublicArrayFreezesTheStatsSubset(): void
    {
        $snapshot = new ShareSnapshot('s1', 'tok', null, 'Florian', $this->payload());

        $public = $snapshot->toPublicArray();

        $this->assertSame('Florian', $public['ownerName']);
        $this->assertSame($this->payload(), $public['stats']);
        $this->assertSame(
            $snapshot->createdAt->format(DateTimeInterface::ATOM),
            $public['createdAt'],
        );
    }

    public function testToPublicArrayKeepsOwnerNameEvenWithUser(): void
    {
        $user = new User(
            id: 'u1',
            email: 'florian@example.com',
            passwordHash: 'hash',
            displayName: 'Florian',
        );

        $snapshot = new ShareSnapshot('s1', 'tok', $user, 'Snapshot Name', $this->payload());

        // The denormalized ownerName is independent of the live user displayName.
        $this->assertSame('Snapshot Name', $snapshot->toPublicArray()['ownerName']);
    }
}
