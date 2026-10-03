<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Doctrine;

use App\Manga\Domain\Manga;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Restricts every Manga query to the current user's own series: another
 * account's copy is never found, so it can be neither read nor changed (404).
 * Volumes are only ever reached through their series or a volume entry.
 *
 * Enabled per HTTP request by OwnerFilterListener; it stays disabled in the
 * worker / CLI context (enrichment, cover batches, crawl see every series).
 */
final class MangaOwnerFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if ($targetEntity->getName() !== Manga::class) {
            return '';
        }

        return sprintf('%s.owner_id = %s', $targetTableAlias, $this->getParameter('ownerId'));
    }
}
