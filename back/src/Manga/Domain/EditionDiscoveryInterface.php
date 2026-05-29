<?php

declare(strict_types=1);

namespace App\Manga\Domain;

interface EditionDiscoveryInterface
{
    /**
     * Discover all known editions for a given work title in the requested country's market.
     *
     * @return ExternalEditionDto[]
     */
    public function discoverEditions(string $workTitle, Country $country): array;
}
