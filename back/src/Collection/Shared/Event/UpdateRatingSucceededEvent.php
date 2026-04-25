<?php

declare(strict_types=1);

namespace App\Collection\Shared\Event;

use Symfony\Component\Uid\Uuid;
use App\Shared\Domain\Event\SucceededEventInterface;

final readonly class UpdateRatingSucceededEvent implements SucceededEventInterface
{
    public string $correlationId;

    public function __construct(
        public string $collectionEntryId,
        public int $rating,
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? Uuid::v4()->toRfc4122();
    }
}
