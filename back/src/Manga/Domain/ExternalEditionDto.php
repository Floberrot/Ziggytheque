<?php

declare(strict_types=1);

namespace App\Manga\Domain;

final readonly class ExternalEditionDto
{
    public function __construct(
        public string $publisher,
        public ?string $editionLabel,
        public ?int $year,
        public string $language,
        public ?string $coverUrl,
        public ?int $volumeCount,
        public ?string $sampleIsbn,
        public string $source,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'publisher'   => $this->publisher,
            'editionLabel' => $this->editionLabel,
            'year'        => $this->year,
            'language'    => $this->language,
            'coverUrl'    => $this->coverUrl,
            'volumeCount' => $this->volumeCount,
            'sampleIsbn'  => $this->sampleIsbn,
            'source'      => $this->source,
        ];
    }
}
