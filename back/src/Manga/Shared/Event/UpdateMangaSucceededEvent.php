<?php

declare(strict_types=1);

namespace App\Manga\Shared\Event;

use Symfony\Component\Uid\Uuid;
use App\Shared\Domain\Event\SucceededEventInterface;

final readonly class UpdateMangaSucceededEvent implements SucceededEventInterface
{
    public string $correlationId;

    public function __construct(
        public string $mangaId,
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? Uuid::v4()->toRfc4122();
    }
}
