<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Country;

/**
 * Discovers Spanish editions from the Biblioteca Nacional de España (BNE) via
 * its SRU API, using the Dublin Core record schema. The legal deposit
 * (depósito legal) makes the BNE authoritative for works published in Spain.
 */
final readonly class BneEditionDiscoveryClient extends AbstractSruEditionDiscoveryClient
{
    protected function country(): Country
    {
        return Country::Spain;
    }

    protected function source(): string
    {
        return 'bne';
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
        return sprintf('dc.title all "%s"', $this->escapeCqlValue($workTitle));
    }

    protected function maximumRecords(): int
    {
        return 100;
    }
}
