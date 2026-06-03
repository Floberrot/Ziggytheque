<?php

declare(strict_types=1);

namespace App\Manga\Application\TranslateSummary;

/**
 * Asks for a manga summary to be translated into French.
 *
 * Only English → French is supported for now (see CLAUDE.md / feature scope),
 * so the languages are fixed by the handler rather than carried on the query.
 */
final readonly class TranslateSummaryQuery
{
    public function __construct(public string $text)
    {
    }
}
