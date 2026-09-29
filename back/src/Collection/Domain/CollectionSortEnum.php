<?php

declare(strict_types=1);

namespace App\Collection\Domain;

enum CollectionSortEnum: string
{
    // Default: alphabetical by work, so every edition of a work sits together.
    case TitleAsc   = 'title_asc';
    case AddedDesc  = 'added_desc';
    case RatingAsc  = 'rating_asc';
    case RatingDesc = 'rating_desc';
}
