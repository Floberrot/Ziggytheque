<?php

declare(strict_types=1);

namespace App\Manga\Domain;

final readonly class PriceOfferDto
{
    public function __construct(
        public PriceKindEnum $kind,
        public string $merchant,
        public string $merchantLogo,
        public float $amount,
        public string $currency,
        public ?string $url,
        public ?string $imageUrl,
        public string $source,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'kind'         => $this->kind->value,
            'merchant'     => $this->merchant,
            'merchantLogo' => $this->merchantLogo,
            'amount'       => $this->amount,
            'currency'     => $this->currency,
            'url'          => $this->url,
            'imageUrl'     => $this->imageUrl,
            'source'       => $this->source,
        ];
    }
}
