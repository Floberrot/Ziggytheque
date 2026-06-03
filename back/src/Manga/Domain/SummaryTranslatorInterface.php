<?php

declare(strict_types=1);

namespace App\Manga\Domain;

/**
 * Translates free-text manga summaries from one language to another.
 *
 * Implementations call an external translation service; the application layer
 * stays agnostic of the provider.
 */
interface SummaryTranslatorInterface
{
    /**
     * Returns $text translated into $targetLanguage. Both language codes are
     * ISO 639-1 (e.g. "en", "fr").
     */
    public function translate(string $text, string $sourceLanguage, string $targetLanguage): string;
}
