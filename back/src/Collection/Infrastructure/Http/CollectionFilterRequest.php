<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Http;

use App\Collection\Domain\CollectionSortEnum;
use App\Collection\Domain\ReadingStatusEnum;
use App\Manga\Domain\GenreEnum;
use Symfony\Component\Validator\Constraints as Assert;

final class CollectionFilterRequest
{
    public ?string $search = null;
    public ?GenreEnum $genre = null;
    public ?string $edition = null;
    public ?ReadingStatusEnum $readingStatus = null;
    public ?CollectionSortEnum $sort = null;
    public bool $followed = false;

    #[Assert\Positive]
    public int $page = 1;
}
