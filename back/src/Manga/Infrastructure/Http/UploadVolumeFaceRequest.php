<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UploadVolumeFaceRequest
{
    public function __construct(
        #[Assert\Choice(choices: ['cover', 'spine', 'back'])]
        public string $face = '',
        #[Assert\NotBlank]
        public string $image = '',
        public string $contentType = 'image/jpeg',
    ) {
    }
}
