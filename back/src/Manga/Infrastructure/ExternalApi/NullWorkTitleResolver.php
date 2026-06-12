<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\WorkTitleResolverInterface;

/** No-op resolver used in tests: never reaches the network, always falls back to the query. */
final readonly class NullWorkTitleResolver implements WorkTitleResolverInterface
{
    public function resolve(string $query, ?string $language): ?string
    {
        return null;
    }
}
