<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\EditionProviderInterface;

final readonly class NullEditionProvider implements EditionProviderInterface
{
    public function findEditions(string $workTitle, ?string $author, ?string $language): array
    {
        return [];
    }
}
