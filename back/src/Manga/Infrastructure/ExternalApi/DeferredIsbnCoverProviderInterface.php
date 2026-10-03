<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaCoverProviderInterface;
use App\Manga\Domain\MangaVolumeCoverDto;
use Closure;

/**
 * A cover source that sends its ISBN lookup without waiting for the answer (HttpClient
 * responses are lazy), so {@see CompositeMangaCoverApiClient::findAllByIsbn()} starts
 * every source before it reads any of them.
 */
interface DeferredIsbnCoverProviderInterface extends MangaCoverProviderInterface
{
    /**
     * Sends the first request of the lookup and returns at once. The returned closure
     * waits for the answer and finishes the lookup; it never throws — a failing source
     * finds no cover.
     *
     * @return Closure(): ?MangaVolumeCoverDto
     */
    public function requestByIsbn(Isbn $isbn): Closure;
}
