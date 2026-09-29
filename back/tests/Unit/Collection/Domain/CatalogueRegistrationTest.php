<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Domain;

use App\Collection\Domain\CatalogueRegistration;
use PHPUnit\Framework\TestCase;

final class CatalogueRegistrationTest extends TestCase
{
    public function testToArrayAndAddedNothing(): void
    {
        $registration = new CatalogueRegistration(
            collectionEntryId: 'ce1',
            mangaId: 'm1',
            seriesCreated: true,
            entryCreated: false,
            totalVolumes: 14,
            addedNumbers: [2],
            alreadyOwnedNumbers: [1],
            volumeEntryIds: [1 => 've1', 2 => 've2'],
        );

        $array = $registration->toArray();

        $this->assertFalse($registration->addedNothing());
        $this->assertSame('ce1', $array['collectionEntryId']);
        $this->assertSame('m1', $array['mangaId']);
        $this->assertTrue($array['seriesCreated']);
        $this->assertFalse($array['entryCreated']);
        $this->assertSame(14, $array['totalVolumes']);
        $this->assertSame([2], $array['addedNumbers']);
        $this->assertSame([1], $array['alreadyOwnedNumbers']);
        $this->assertSame('{"1":"ve1","2":"ve2"}', json_encode($array['volumeEntryIds']));
    }

    public function testAddedNothingWhenEveryTomeWasOwned(): void
    {
        $registration = new CatalogueRegistration('ce1', 'm1', false, false, 3, [], [1], [1 => 've1']);

        $this->assertTrue($registration->addedNothing());
        $this->assertSame('{"1":"ve1"}', json_encode($registration->toArray()['volumeEntryIds']));
    }
}
