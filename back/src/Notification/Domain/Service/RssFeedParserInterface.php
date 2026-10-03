<?php

declare(strict_types=1);

namespace App\Notification\Domain\Service;

interface RssFeedParserInterface
{
    /**
     * Downloads and reads one feed — once per crawl, whatever the number of series it is
     * matched against.
     *
     * @return list<RssFeedItem>
     *
     * @throws RssFeedParserException when the feed answers an error or is not a feed
     */
    public function parse(string $feedUrl): array;
}
