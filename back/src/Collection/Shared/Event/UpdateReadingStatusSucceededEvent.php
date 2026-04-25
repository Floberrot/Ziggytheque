<?php

declare(strict_types=1);

namespace App\Collection\Shared\Event;

use Symfony\Component\Uid\Uuid;
use App\Shared\Domain\Event\SucceededEventInterface;

final readonly class UpdateReadingStatusSucceededEvent implements SucceededEventInterface
{
    public string $correlationId;

    public function __construct(
        public string $collectionEntryId,
        public string $status,
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? Uuid::v4()->toRfc4122();
    }
}
