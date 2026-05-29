<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Country;

/**
 * Discovers German editions from the Deutsche Nationalbibliothek (DNB) via its
 * SRU API, using the Dublin Core (oai_dc) record schema. The legal deposit
 * (Pflichtexemplar) makes the DNB authoritative for works published in Germany.
 */
final readonly class DnbEditionDiscoveryClient extends AbstractSruEditionDiscoveryClient
{
    protected function country(): Country
    {
        return Country::Germany;
    }

    protected function source(): string
    {
        return 'dnb';
    }

    protected function sruVersion(): string
    {
        return '1.1';
    }

    protected function recordSchema(): string
    {
        return 'oai_dc';
    }

    protected function buildQuery(string $workTitle): string
    {
        // WOE = "Wörter aus dem Titel" (words from the title).
        return sprintf('WOE all "%s"', $this->escapeCqlValue($workTitle));
    }

    protected function maximumRecords(): int
    {
        return 100; // DNB server-side cap is 100.
    }
}
