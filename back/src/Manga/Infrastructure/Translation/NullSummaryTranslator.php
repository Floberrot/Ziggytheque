<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Translation;

use App\Manga\Domain\SummaryTranslatorInterface;

/** No-op stub used in tests to avoid real HTTP calls; echoes the input back. */
final readonly class NullSummaryTranslator implements SummaryTranslatorInterface
{
    public function translate(string $text, string $sourceLanguage, string $targetLanguage): string
    {
        return $text;
    }
}
