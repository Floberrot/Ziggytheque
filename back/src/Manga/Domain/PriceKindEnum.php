<?php

declare(strict_types=1);

namespace App\Manga\Domain;

enum PriceKindEnum: string
{
    case MerchantLive       = 'merchant_live';
    case PublisherReference = 'publisher_reference';
}
