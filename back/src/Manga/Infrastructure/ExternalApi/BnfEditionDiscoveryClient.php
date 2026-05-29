<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Country;

/**
 * Discovers French editions from the Bibliothèque nationale de France (BnF)
 * via its SRU API, using the Dublin Core record schema. The legal deposit
 * (dépôt légal) makes the BnF authoritative for works published in France.
 */
final readonly class BnfEditionDiscoveryClient extends AbstractSruEditionDiscoveryClient
{
    protected function country(): Country
    {
        return Country::France;
    }

    protected function source(): string
    {
        return 'bnf';
    }

    protected function sruVersion(): string
    {
        return '1.2';
    }

    protected function recordSchema(): string
    {
        return 'dublincore';
    }

    protected function buildQuery(string $workTitle): string
    {
        return sprintf('bib.title all "%s"', $this->escapeCqlValue($workTitle));
    }

    protected function maximumRecords(): int
    {
        return 500; // BnF supports up to 1000; 500 gives full coverage without over-fetching.
    }
}
