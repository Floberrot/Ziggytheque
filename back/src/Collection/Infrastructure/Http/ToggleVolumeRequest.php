<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Http;

use App\Collection\Domain\VolumeToggleFieldEnum;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ToggleVolumeRequest
{
    public function __construct(
        #[Assert\NotNull]
        public VolumeToggleFieldEnum $field,
    ) {
    }
}
