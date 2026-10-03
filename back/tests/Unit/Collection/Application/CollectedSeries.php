<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application;

use App\Auth\Domain\User;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\VolumeEntry;
use App\Manga\Domain\Manga;
use App\Manga\Domain\Volume;
use LogicException;

/** A series in a collection, every tome tracked: tome N has volume id "volume-N" and entry id "tome-N". */
final class CollectedSeries
{
    public static function withTomes(int $tomes, ?User $owner = null): CollectionEntry
    {
        $manga = new Manga(id: 'manga-1', title: 'Berserk', edition: 'Glénat', language: 'fr', owner: $owner);
        $entry = new CollectionEntry(id: 'entry-1', manga: $manga, owner: $owner);

        for ($number = 1; $number <= $tomes; $number++) {
            $volume = new Volume(id: 'volume-' . $number, manga: $manga, number: $number);
            $manga->addVolume($volume);
            $entry->volumeEntries->add(new VolumeEntry(id: 'tome-' . $number, collectionEntry: $entry, volume: $volume));
        }

        return $entry;
    }

    public static function tome(CollectionEntry $entry, int $number): VolumeEntry
    {
        return $entry->volumeEntryForNumber($number)
            ?? throw new LogicException(sprintf('The series has no tome %d.', $number));
    }
}
