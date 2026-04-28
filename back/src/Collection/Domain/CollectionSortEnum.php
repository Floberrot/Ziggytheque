<?php

declare(strict_types=1);

namespace App\Collection\Domain;

enum CollectionSortEnum: string
{
    case RatingAsc  = 'rating_asc';
    case RatingDesc = 'rating_desc';
}
