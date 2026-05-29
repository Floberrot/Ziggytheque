<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Country;

/**
 * Discovers Japanese editions from the National Diet Library (NDL) via its SRU
 * API, using the simplified DC-NDL record schema. The legal deposit (法定納本)
 * makes the NDL authoritative for works published in Japan.
 *
 * NDL records primarily carry Japanese-script titles; popular series also
 * index a romanized alternate title. Both fields are queried so that a
 * Latin-script search ("Dragon Ball") and a kana search ("ドラゴンボール") both
 * resolve to the same set of records.
 */
final readonly class NdlEditionDiscoveryClient extends AbstractSruEditionDiscoveryClient
{
    protected function country(): Country
    {
        return Country::Japan;
    }

    protected function source(): string
    {
        return 'ndl';
    }

    protected function sruVersion(): string
    {
        return '1.2';
    }

    protected function recordSchema(): string
    {
        return 'dcndl_simple';
    }

    protected function buildQuery(string $workTitle): string
    {
        // Search title AND alternative (romanized/English) title so that both
        // Latin-script ("Dragon Ball") and kana queries ("ドラゴンボール") find results.
        $escaped = $this->escapeCqlValue($workTitle);
        return sprintf('title="%s" OR alternative="%s"', $escaped, $escaped);
    }

    protected function maximumRecords(): int
    {
        return 200; // NDL server cap is 200.
    }
}
