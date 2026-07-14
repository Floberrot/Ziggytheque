<?php

declare(strict_types=1);

namespace App\Notification\Application\GetActivityLogs;

use App\Shared\Application\Pagination\AbstractPaginatedQuery;
use DateTimeImmutable;
use Exception;

final readonly class GetActivityLogsQuery extends AbstractPaginatedQuery
{
    public ?DateTimeImmutable $from;
    public ?DateTimeImmutable $to;

    public function __construct(
        public ?string $eventType = null,
        public ?string $status = null,
        public ?string $collectionEntryId = null,
        public ?string $ownerId = null,
        ?string $from = null,
        ?string $to = null,
        public ?string $search = null,
        int $page = 1,
        int $limit = 50,
    ) {
        parent::__construct($page, $limit);

        $this->from = self::parseDate($from);
        $this->to   = self::parseDate($to);
    }

    /** Invalid or empty date strings are ignored (filter not applied). */
    private static function parseDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }
}
