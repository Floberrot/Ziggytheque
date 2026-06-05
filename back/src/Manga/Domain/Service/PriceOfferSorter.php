<?php

declare(strict_types=1);

namespace App\Manga\Domain\Service;

use App\Manga\Domain\PriceKindEnum;
use App\Manga\Domain\PriceOfferDto;

final readonly class PriceOfferSorter
{
    /**
     * Sorts price offers: merchant_live offers first (sorted by amount ASC),
     * then publisher_reference offers (sorted by amount ASC).
     *
     * @param  list<PriceOfferDto> $offers
     * @return list<PriceOfferDto>
     */
    public function sort(array $offers): array
    {
        usort($offers, static function (PriceOfferDto $alpha, PriceOfferDto $bravo): int {
            $kindOrder = static fn (PriceKindEnum $kind): int => match ($kind) {
                PriceKindEnum::MerchantLive       => 0,
                PriceKindEnum::PublisherReference => 1,
            };

            $kindAlpha = $kindOrder($alpha->kind);
            $kindBravo = $kindOrder($bravo->kind);

            if ($kindAlpha !== $kindBravo) {
                return $kindAlpha <=> $kindBravo;
            }

            return $alpha->amount <=> $bravo->amount;
        });

        return $offers;
    }
}
