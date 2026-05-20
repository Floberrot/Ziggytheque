<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Doctrine;

use App\Notification\Domain\Article;
use App\Notification\Domain\Notification;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Restricts every Notification / Article query to rows owned by the current
 * user, so notifications and news articles never leak across accounts.
 *
 * Enabled per HTTP request by OwnerFilterListener; it stays disabled in the
 * worker / CLI context where the crawl creates articles for every owner.
 *
 * ActivityLog is intentionally excluded — the journal is an admin-only audit
 * view that must keep its global, cross-account picture (system crawl events
 * have no owner at all).
 */
final class NotificationOwnerFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        return match ($targetEntity->getName()) {
            Notification::class, Article::class => sprintf(
                '%s.owner_id = %s',
                $targetTableAlias,
                $this->getParameter('ownerId'),
            ),
            default => '',
        };
    }
}
