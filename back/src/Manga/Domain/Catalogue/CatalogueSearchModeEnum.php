<?php

declare(strict_types=1);

namespace App\Manga\Domain\Catalogue;

enum CatalogueSearchModeEnum: string
{
    case Title  = 'title';
    case Author = 'author';
    case Isbn   = 'isbn';
}
