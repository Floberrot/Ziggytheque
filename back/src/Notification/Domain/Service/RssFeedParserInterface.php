<?php

declare(strict_types=1);

namespace App\Notification\Domain\Service;

use App\Collection\Domain\CollectionEntry;

interface RssFeedParserInterface
{
    public function parse(string $feedUrl, string $mangaTitle, CollectionEntry $entry): RssFetchResult;
}
