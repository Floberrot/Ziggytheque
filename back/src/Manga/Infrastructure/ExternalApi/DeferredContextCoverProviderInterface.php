<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\MangaVolumeCoverDto;
use App\Manga\Domain\MultiContextCoverProviderInterface;
use Closure;

/**
 * A title-search cover source that sends its search without waiting for the answer
 * (HttpClient responses are lazy), so
 * {@see CompositeMangaCoverApiClient::findAllByContext()} starts every source before it
 * reads any of them.
 */
interface DeferredContextCoverProviderInterface extends MultiContextCoverProviderInterface
{
    /**
     * Sends the first request of the search and returns at once. The returned closure
     * waits for the answer and finishes the search; it never throws — a failing source
     * finds no cover.
     *
     * @return Closure(): list<MangaVolumeCoverDto>
     */
    public function requestAllByContext(
        string $mangaTitle,
        ?string $edition,
        int $volumeNumber,
        string $language = 'fr',
    ): Closure;
}
