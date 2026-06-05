<?php

declare(strict_types=1);

namespace App\Manga\Domain;

interface EditionProviderInterface
{
    /**
     * Returns every edition found for the given work title across all underlying
     * sources. The caller is responsible for deduplication via EditionGrouper.
     *
     * @return list<ExternalEditionDto>
     */
    public function findEditions(string $workTitle, ?string $author, ?string $language): array;
}
