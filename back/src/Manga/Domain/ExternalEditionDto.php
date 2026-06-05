<?php

declare(strict_types=1);

namespace App\Manga\Domain;

final readonly class ExternalEditionDto
{
    public function __construct(
        public string $workTitle,
        public string $editionLabel,
        public ?string $publisher,
        public string $language,
        public ?string $country,
        public EditionFormatEnum $format,
        public ?int $volumeCount,
        public ?string $isbnSample,
        public ?string $coverUrl,
        public string $source,
        public ?string $externalId = null,
        // Curated edition line ("Perfect Edition", "Édition originale", "Coffret"…)
        // derived from the record title; null when the record is a plain volume.
        public ?string $editionLine = null,
    ) {
    }

    public function withCoverUrl(string $coverUrl): self
    {
        return new self(
            workTitle:    $this->workTitle,
            editionLabel: $this->editionLabel,
            publisher:    $this->publisher,
            language:     $this->language,
            country:      $this->country,
            format:       $this->format,
            volumeCount:  $this->volumeCount,
            isbnSample:   $this->isbnSample,
            coverUrl:     $coverUrl,
            source:       $this->source,
            externalId:   $this->externalId,
            editionLine:  $this->editionLine,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'workTitle'    => $this->workTitle,
            'editionLabel' => $this->editionLabel,
            'publisher'    => $this->publisher,
            'language'     => $this->language,
            'country'      => $this->country,
            'format'       => $this->format->value,
            'volumeCount'  => $this->volumeCount,
            'isbnSample'   => $this->isbnSample,
            'coverUrl'     => $this->coverUrl,
            'source'       => $this->source,
            'externalId'   => $this->externalId,
            'editionLine'  => $this->editionLine,
        ];
    }
}
