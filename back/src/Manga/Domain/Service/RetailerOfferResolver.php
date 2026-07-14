<?php

declare(strict_types=1);

namespace App\Manga\Domain\Service;

use App\Manga\Domain\PriceKindEnum;
use App\Manga\Domain\RetailerEnum;

/**
 * Builds the per-retailer summary out of the flat offers list: each target shop
 * (Amazon, Fnac, eBay) reports its cheapest live offer, or an honest "not_found".
 *
 * Works on the serialized offer arrays (the exact shape stored in the price cache),
 * so cached and fresh results go through the same code path.
 */
final readonly class RetailerOfferResolver
{
    public const string STATUS_FOUND     = 'found';
    public const string STATUS_NOT_FOUND = 'not_found';

    /**
     * @param  list<array<string, mixed>> $offers serialized PriceOfferDto entries
     * @return list<array{retailer: string, label: string, status: string, bestOffer: array<string, mixed>|null}>
     */
    public function resolve(array $offers): array
    {
        /** @var array<string, array<string, mixed>> $bestOfferByRetailer */
        $bestOfferByRetailer = [];

        foreach ($offers as $offer) {
            // Publisher reference prices (e.g. Google Play) never feed the retailer blocks.
            if (($offer['kind'] ?? null) !== PriceKindEnum::MerchantLive->value) {
                continue;
            }

            $retailer = RetailerEnum::fromMerchant((string) ($offer['merchant'] ?? ''));
            if ($retailer === null) {
                continue;
            }

            $currentBest = $bestOfferByRetailer[$retailer->value] ?? null;
            if ($currentBest === null || (float) ($offer['amount'] ?? 0.0) < (float) ($currentBest['amount'] ?? 0.0)) {
                $bestOfferByRetailer[$retailer->value] = $offer;
            }
        }

        $retailerBlocks = [];
        foreach (RetailerEnum::targets() as $retailer) {
            $bestOffer = $bestOfferByRetailer[$retailer->value] ?? null;

            $retailerBlocks[] = [
                'retailer'  => $retailer->value,
                'label'     => $retailer->label(),
                'status'    => $bestOffer !== null ? self::STATUS_FOUND : self::STATUS_NOT_FOUND,
                'bestOffer' => $bestOffer,
            ];
        }

        return $retailerBlocks;
    }
}
