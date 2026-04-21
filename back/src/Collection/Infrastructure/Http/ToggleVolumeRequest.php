<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ToggleVolumeRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['isOwned', 'isRead', 'isWished'])]
        public string $field,
    ) {
    }
}
