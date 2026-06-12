<?php

declare(strict_types=1);

namespace App\Manga\Domain;

/**
 * Resolves the user's query into the work's title in a given target language, so a
 * foreign catalogue can actually be searched. A French user types "L'Attaque des
 * titans", but the Japanese editions are catalogued under "進撃の巨人" and the US ones
 * under "Attack on Titan" — searching them with the French string returns nothing.
 *
 * Returns null when no translation is needed or none could be found (caller falls
 * back to the original query).
 */
interface WorkTitleResolverInterface
{
    public function resolve(string $query, ?string $language): ?string;
}
