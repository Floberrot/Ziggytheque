<?php

declare(strict_types=1);

namespace App\Manga\Shared\Event;

use App\Shared\Domain\Event\StartedEventInterface;
use Symfony\Component\Uid\Uuid;

/** A series (title, publisher, special edition, cover) is being corrected by its owner. */
final readonly class UpdateMangaStartedEvent implements StartedEventInterface
{
    public string $correlationId;

    public function __construct(
        public string $mangaId,
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? Uuid::v4()->toRfc4122();
    }
}
