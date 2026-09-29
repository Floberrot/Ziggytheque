<?php

declare(strict_types=1);

namespace App\Manga\Domain\Service;

use App\Manga\Domain\Catalogue\CatalogueEdition;
use App\Manga\Domain\Catalogue\CatalogueInterface;
use App\Manga\Domain\Catalogue\CatalogueSearchModeEnum;
use App\Manga\Domain\Catalogue\CatalogueSearchResult;
use App\Manga\Domain\Catalogue\IsbnIdentification;
use App\Manga\Domain\EditionIdentity;
use App\Manga\Domain\Exception\CatalogueQueryTooShortException;
use App\Manga\Domain\Exception\IsbnNotInCatalogueException;
use App\Manga\Domain\Isbn;

/**
 * The three ways to find a manga — by title, by author, by ISBN (scan) — answered
 * with series (work × publisher × special edition), French editions only.
 */
final readonly class CatalogueSearch
{
    private const int MIN_QUERY_LENGTH = 2;

    public function __construct(
        private CatalogueInterface $catalogue,
        private CatalogueTitleParser $titleParser,
        private CatalogueEditionAssembler $assembler,
        private PublisherNormalizer $publisherNormalizer,
    ) {
    }

    public function search(string $query, CatalogueSearchModeEnum $mode): CatalogueSearchResult
    {
        $trimmed = trim($query);
        if (mb_strlen($trimmed) < self::MIN_QUERY_LENGTH) {
            throw new CatalogueQueryTooShortException();
        }

        if ($mode === CatalogueSearchModeEnum::Isbn) {
            $identification = $this->identifyIsbn(Isbn::fromString($trimmed));

            return new CatalogueSearchResult(
                searchedText: $identification->edition->workTitle,
                requestedVolume: $identification->volume->number,
                editions: [$identification->edition],
            );
        }

        if ($mode === CatalogueSearchModeEnum::Author) {
            return new CatalogueSearchResult(
                searchedText: $trimmed,
                requestedVolume: null,
                editions: $this->assembler->assemble($this->catalogue->searchByAuthor($trimmed)),
            );
        }

        $parsedQuery = $this->titleParser->parseQuery($trimmed);

        return new CatalogueSearchResult(
            searchedText: $parsedQuery['text'],
            requestedVolume: $parsedQuery['volumeNumber'],
            editions: $this->assembler->assemble(
                $this->catalogue->searchByTitle($parsedQuery['text']),
                $parsedQuery['text'],
            ),
        );
    }

    /**
     * Every volume the catalogue knows for one series — used to show the full tome
     * list once a series is picked. Null when the catalogue has no such series.
     */
    public function edition(EditionIdentity $identity): ?CatalogueEdition
    {
        if (mb_strlen(trim($identity->workTitle)) < self::MIN_QUERY_LENGTH) {
            throw new CatalogueQueryTooShortException();
        }

        $searchText = trim($identity->workTitle . ' ' . ($identity->specialEdition ?? ''));

        foreach ($this->assembler->assemble($this->catalogue->searchByTitle($searchText)) as $edition) {
            if ($edition->identity()->matches($identity, $this->publisherNormalizer)) {
                return $edition;
            }
        }

        return null;
    }

    /**
     * Places a scanned ISBN in its series. The book's own record gives the work; its
     * siblings (same work) are fetched too, so the series' length and a special
     * edition written after the volume number can be recognised.
     */
    public function identifyIsbn(Isbn $isbn): IsbnIdentification
    {
        $records = $this->catalogue->findByIsbn($isbn);
        if ($records === []) {
            throw new IsbnNotInCatalogueException($isbn->value);
        }

        $siblings = $this->catalogue->searchByTitle($records[0]->workTitle);

        foreach ($this->assembler->assemble([...$records, ...$siblings]) as $edition) {
            $volume = $edition->volumeByIsbn($isbn);
            if ($volume !== null) {
                return new IsbnIdentification($edition, $volume);
            }
        }

        throw new IsbnNotInCatalogueException($isbn->value);
    }
}
