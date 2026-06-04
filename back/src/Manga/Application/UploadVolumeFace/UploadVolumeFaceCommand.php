<?php

declare(strict_types=1);

namespace App\Manga\Application\UploadVolumeFace;

use App\Manga\Domain\VolumeFace;

final readonly class UploadVolumeFaceCommand
{
    public function __construct(
        public string $mangaId,
        public string $volumeId,
        public VolumeFace $face,
        public string $imageBase64,
        public string $contentType,
    ) {
    }
}
