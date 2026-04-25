<?php

declare(strict_types=1);

namespace App\Manga\Shared\Event;

use Symfony\Component\Uid\Uuid;
use App\Shared\Domain\Event\StartedEventInterface;

final readonly class AddVolumeStartedEvent implements StartedEventInterface
{
    public string $correlationId;

    public function __construct(
        public string $mangaId,
        public int $number,
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? Uuid::v4()->toRfc4122();
    }
}
