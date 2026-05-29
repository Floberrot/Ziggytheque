<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Country;

/**
 * Discovers US editions from the Library of Congress (LOC) via its SRU API,
 * using the Dublin Core record schema. LOC is the authoritative bibliographic
 * source for works published or registered in the United States.
 */
final readonly class LocEditionDiscoveryClient extends AbstractSruEditionDiscoveryClient
{
    protected function country(): Country
    {
        return Country::UnitedStates;
    }

    protected function source(): string
    {
        return 'loc';
    }

    protected function sruVersion(): string
    {
        return '1.1';
    }

    protected function recordSchema(): string
    {
        return 'dc';
    }

    protected function buildQuery(string $workTitle): string
    {
        // "dc.title" is the standard CQL index for the LOC catalog.
        return sprintf('dc.title all "%s"', $this->escapeCqlValue($workTitle));
    }

    protected function maximumRecords(): int
    {
        return 100; // LOC SRU server cap is 100.
    }
}
