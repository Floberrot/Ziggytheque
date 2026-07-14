<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain\Service;

use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Domain\Service\ActivityLogPurger;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class ActivityLogPurgerTest extends TestCase
{
    public function testPurgeDeletesLogsOlderThanGivenDays(): void
    {
        $repository = $this->createMock(ActivityLogRepositoryInterface::class);

        $repository->expects($this->once())
            ->method('deleteOlderThan')
            ->with($this->callback(function (DateTimeImmutable $cutoff): bool {
                $expected = new DateTimeImmutable('-30 days');
                $driftSeconds = abs($cutoff->getTimestamp() - $expected->getTimestamp());
                $this->assertLessThan(5, $driftSeconds);
                return true;
            }))
            ->willReturn(12);

        $purger = new ActivityLogPurger($repository);

        $this->assertSame(12, $purger->purgeOlderThanDays(30));
    }

    public function testPurgeDefaultsToNinetyDays(): void
    {
        $repository = $this->createMock(ActivityLogRepositoryInterface::class);

        $repository->expects($this->once())
            ->method('deleteOlderThan')
            ->with($this->callback(function (DateTimeImmutable $cutoff): bool {
                $expected = new DateTimeImmutable('-90 days');
                $driftSeconds = abs($cutoff->getTimestamp() - $expected->getTimestamp());
                $this->assertLessThan(5, $driftSeconds);
                return true;
            }))
            ->willReturn(0);

        $purger = new ActivityLogPurger($repository);

        $this->assertSame(0, $purger->purgeOlderThanDays());
    }

    public function testPurgeRejectsRetentionBelowOneDay(): void
    {
        $repository = $this->createMock(ActivityLogRepositoryInterface::class);
        $repository->expects($this->never())->method('deleteOlderThan');

        $purger = new ActivityLogPurger($repository);

        $this->expectException(InvalidArgumentException::class);
        $purger->purgeOlderThanDays(0);
    }
}
