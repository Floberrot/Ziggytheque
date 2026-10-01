<?php

declare(strict_types=1);

namespace App\Tests\Doubles\Manga;

use App\Manga\Domain\Catalogue\CatalogueInterface;
use App\Manga\Domain\Catalogue\CatalogueRecord;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\TextFold;

/**
 * Catalogue seeded by the test: title and author searches match on folded
 * substrings, ISBN lookups on the exact ISBN (or what `answerIsbn()` set). Never
 * reaches the internet.
 */
final class InMemoryCatalogue implements CatalogueInterface
{
    /** @var list<CatalogueRecord> */
    private array $records = [];

    /** @var array<string, list<CatalogueRecord>> ISBN → records the catalogue answers for it */
    private array $isbnAnswers = [];

    public function add(CatalogueRecord ...$records): void
    {
        foreach ($records as $record) {
            $this->records[] = $record;
        }
    }

    /** A catalogue whose record for that ISBN states another ISBN (or none). */
    public function answerIsbn(string $isbn, CatalogueRecord ...$records): void
    {
        $this->isbnAnswers[Isbn::fromString($isbn)->value] = array_values($records);
        $this->add(...$records);
    }

    public function reset(): void
    {
        $this->records     = [];
        $this->isbnAnswers = [];
    }

    public function searchByTitle(string $title): array
    {
        $foldedTitle = TextFold::fold($title);

        return array_values(array_filter(
            $this->records,
            static fn (CatalogueRecord $record): bool => str_contains(
                TextFold::fold($record->workTitle . ' ' . ($record->headQualifier ?? '')),
                $foldedTitle,
            ),
        ));
    }

    public function searchByAuthor(string $author): array
    {
        $foldedAuthor = TextFold::fold($author);

        return array_values(array_filter(
            $this->records,
            static fn (CatalogueRecord $record): bool => str_contains(TextFold::fold($record->author), $foldedAuthor),
        ));
    }

    public function findByIsbn(Isbn $isbn): array
    {
        if (isset($this->isbnAnswers[$isbn->value])) {
            return $this->isbnAnswers[$isbn->value];
        }

        return array_values(array_filter(
            $this->records,
            static fn (CatalogueRecord $record): bool => $record->isbn !== null && $record->isbn->equals($isbn),
        ));
    }
}
