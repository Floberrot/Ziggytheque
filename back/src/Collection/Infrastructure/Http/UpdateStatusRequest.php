<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Http;

use App\Collection\Domain\ReadingStatusEnum;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateStatusRequest
{
    public function __construct(
        #[Assert\NotNull]
        public ReadingStatusEnum $status,
    ) {
    }
}
