<?php

declare(strict_types=1);

namespace App\Notification\Domain\Service;

use App\Collection\Domain\CollectionEntry;
use App\Notification\Domain\Article;
use App\Notification\Domain\ArticleRepositoryInterface;
use App\Shared\Domain\ValueObject\WebLink;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;
use Transliterator;

/**
 * Links the items of a feed, already downloaded, to one followed series: the items that
 * name the work become its articles (once each — an item already linked is skipped).
 */
final readonly class RssArticleCollector
{
    /** News published before the feature existed are never linked. */
    private const string OLDEST_NEWS = '2026-04-01';

    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
        private MangaArticleMatcher $matcher,
    ) {
    }

    /** @param list<RssFeedItem> $feedItems */
    public function collect(array $feedItems, CollectionEntry $entry, string $mangaTitle): RssFetchResult
    {
        $newCount        = 0;
        $snippetKeywords = $this->matcher->coreTitleWords($mangaTitle);
        $oldestNews      = new DateTimeImmutable(self::OLDEST_NEWS);

        foreach ($feedItems as $feedItem) {
            // The work must be named in the article — a loose keyword overlap is not enough.
            // The link ends up in an href: http(s) only.
            if (
                !WebLink::isWebLink($feedItem->url)
                || !$this->matcher->mentions($mangaTitle, $feedItem->title . ' ' . $feedItem->description)
            ) {
                continue;
            }

            if ($feedItem->publishedAt !== null && $feedItem->publishedAt < $oldestNews) {
                continue;
            }

            if ($this->articleRepository->existsByCollectionEntryAndUrl($entry->id, $feedItem->url)) {
                continue;
            }

            $article = new Article(
                id: Uuid::v4()->toRfc4122(),
                collectionEntry: $entry,
                title: mb_substr($feedItem->title, 0, 500),
                url: $feedItem->url,
                sourceName: 'rss',
                author: null,
                imageUrl: $feedItem->imageUrl,
                publishedAt: $feedItem->publishedAt,
                snippet: $this->extractSnippet($feedItem->description, $snippetKeywords),
            );
            $article->owner = $entry->owner;
            $this->articleRepository->save($article);
            ++$newCount;
        }

        return new RssFetchResult($newCount, count($feedItems));
    }

    /**
     * About 220 characters of the description around the first title word it contains
     * (its start when none does). The matcher hands the words folded (lower case, no
     * accents), so the description is searched folded the same way — an accented title
     * ("Pokémon") still finds its place in the text.
     *
     * @param list<string> $keywords
     */
    private function extractSnippet(string $text, array $keywords): ?string
    {
        if ($text === '' || $keywords === []) {
            return null;
        }

        [$foldedText, $originalOffsets] = $this->foldWithOffsets($text);
        $firstPosition = null;

        foreach ($keywords as $keyword) {
            $position = mb_strpos($foldedText, $keyword);
            if ($position !== false && ($firstPosition === null || $position < $firstPosition)) {
                $firstPosition = $position;
            }
        }

        if ($firstPosition === null) {
            return mb_substr($text, 0, 200);
        }

        $start   = max(0, $originalOffsets[$firstPosition] - 80);
        $excerpt = mb_substr($text, $start, 220);
        if ($start > 0) {
            $excerpt = '…' . ltrim($excerpt);
        }

        return rtrim($excerpt) . (mb_strlen($text) > $start + 220 ? '…' : '');
    }

    /**
     * The text in lower case without accents ("Œuvre" → "oeuvre"), and for each folded
     * character the offset, in the original text, of the character it comes from.
     *
     * @return array{string, list<int>}
     */
    private function foldWithOffsets(string $text): array
    {
        $transliterator  = Transliterator::create('Latin-ASCII; Lower()');
        $foldedText      = '';
        $originalOffsets = [];

        foreach (mb_str_split($text) as $offset => $character) {
            $foldedCharacter = $transliterator?->transliterate($character);
            if (!is_string($foldedCharacter)) {
                $foldedCharacter = mb_strtolower($character);
            }

            $foldedText .= $foldedCharacter;
            array_push($originalOffsets, ...array_fill(0, mb_strlen($foldedCharacter), $offset));
        }

        return [$foldedText, $originalOffsets];
    }
}
