<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Doctrine;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\VolumeEntry;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Restricts every CollectionEntry / VolumeEntry query to rows owned by the
 * current user, so collection data is never visible across accounts.
 *
 * Enabled per HTTP request by OwnerFilterListener; it stays disabled in the
 * worker / CLI context so the scheduler keeps its global fan-out view.
 */
final class CollectionOwnerFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        return match ($targetEntity->getName()) {
            CollectionEntry::class => sprintf(
                '%s.owner_id = %s',
                $targetTableAlias,
                $this->getParameter('ownerId'),
            ),
            VolumeEntry::class => sprintf(
                '%s.collection_entry_id IN '
                . '(SELECT owned_entry.id FROM collection_entries owned_entry WHERE owned_entry.owner_id = %s)',
                $targetTableAlias,
                $this->getParameter('ownerId'),
            ),
            default => '',
        };
    }
}
