<?php

declare(strict_types=1);

namespace App\Collection\Application\Get;

use App\Collection\Domain\CollectionSortEnum;
use App\Collection\Domain\ReadingStatusEnum;
use App\Manga\Domain\GenreEnum;
use App\Shared\Application\Pagination\AbstractPaginatedQuery;

final readonly class GetCollectionQuery extends AbstractPaginatedQuery
{
    public function __construct(
        public ?string $search = null,
        public ?GenreEnum $genre = null,
        public ?string $edition = null,
        public ?ReadingStatusEnum $readingStatus = null,
        public ?CollectionSortEnum $sort = null,
        public bool $followedOnly = false,
        int $page = 1,
        int $limit = 20,
    ) {
        parent::__construct($page, $limit);
    }
}
