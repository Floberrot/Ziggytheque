<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain\Service;

use App\Notification\Domain\Service\MangaArticleMatcher;
use App\Notification\Domain\Service\RssArticleCollector;
use App\Notification\Domain\Service\RssFeedItem;
use App\Tests\Doubles\Notification\FollowedEntryFactory;
use App\Tests\Doubles\Notification\InMemoryArticleRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RssArticleCollectorTest extends TestCase
{
    private InMemoryArticleRepository $articleRepository;
    private RssArticleCollector $collector;

    protected function setUp(): void
    {
        $this->articleRepository = new InMemoryArticleRepository();
        $this->collector         = new RssArticleCollector($this->articleRepository, new MangaArticleMatcher());
    }

    public function testLinksTheItemsThatNameTheWork(): void
    {
        $owner = FollowedEntryFactory::owner('owner-a');
        $entry = FollowedEntryFactory::entry('entry-a', 'One Piece', owner: $owner);

        $result = $this->collector->collect([
            $this->item('One Piece : le tome 110 en mai', 'https://news.example/one-piece-110', 'Glénat annonce One Piece 110.'),
            $this->item('Naruto revient', 'https://news.example/naruto'),
        ], $entry, 'One Piece');

        $this->assertSame(1, $result->newCount);
        $this->assertSame(2, $result->itemsScanned);
        $this->assertCount(1, $this->articleRepository->articles);

        $article = $this->articleRepository->articles[0];
        $this->assertSame($entry, $article->collectionEntry);
        $this->assertSame($owner, $article->owner);
        $this->assertSame('One Piece : le tome 110 en mai', $article->title);
        $this->assertSame('https://news.example/one-piece-110', $article->url);
        $this->assertSame('rss', $article->sourceName);
        $this->assertNull($article->author);
        $this->assertSame('https://news.example/cover.jpg', $article->imageUrl);
        $this->assertSame('Glénat annonce One Piece 110.', $article->snippet);
    }

    public function testMatchesOnTheDescriptionToo(): void
    {
        $result = $this->collector->collect(
            [$this->item('Les sorties de la semaine', 'https://news.example/week', 'Avec Berserk tome 42.')],
            FollowedEntryFactory::entry('entry-a', 'Berserk'),
            'Berserk',
        );

        $this->assertSame(1, $result->newCount);
    }

    public function testMatchesTheTitleThePlanCarriesNotTheCurrentOne(): void
    {
        $entry = FollowedEntryFactory::entry('entry-a', 'One Piece (renamed since)');

        $result = $this->collector->collect([$this->item('One Piece 110', 'https://news.example/a')], $entry, 'One Piece');

        $this->assertSame(1, $result->newCount);
    }

    public function testSkipsLinksThatAreNotWebLinks(): void
    {
        $result = $this->collector->collect([
            $this->item('One Piece 110', 'javascript:alert(1)'),
            $this->item('One Piece 111', ''),
        ], FollowedEntryFactory::entry('entry-a', 'One Piece'), 'One Piece');

        $this->assertSame(0, $result->newCount);
    }

    public function testSkipsNewsOlderThanTheFeature(): void
    {
        $result = $this->collector->collect([
            $this->item('One Piece 100', 'https://news.example/old', publishedAt: new DateTimeImmutable('2026-03-31 23:59:59')),
            $this->item('One Piece 110', 'https://news.example/new', publishedAt: new DateTimeImmutable('2026-04-01 00:00:00')),
            $this->item('One Piece undated', 'https://news.example/undated', publishedAt: null),
        ], FollowedEntryFactory::entry('entry-a', 'One Piece'), 'One Piece');

        $this->assertSame(2, $result->newCount);
        $this->assertSame(
            ['https://news.example/new', 'https://news.example/undated'],
            array_map(static fn ($article) => $article->url, $this->articleRepository->articles),
        );
    }

    public function testAnItemAlreadyLinkedIsNotLinkedAgain(): void
    {
        $entry = FollowedEntryFactory::entry('entry-a', 'One Piece');
        $items = [$this->item('One Piece 110', 'https://news.example/a')];

        $this->collector->collect($items, $entry, 'One Piece');
        $second = $this->collector->collect($items, $entry, 'One Piece');

        $this->assertSame(0, $second->newCount);
        $this->assertCount(1, $this->articleRepository->articles);
    }

    /** The same item links once to each followed copy — every account gets its own article. */
    public function testTheSameItemLinksToEachFollowedCopy(): void
    {
        $items  = [$this->item('One Piece 110', 'https://news.example/a')];
        $first  = FollowedEntryFactory::entry('entry-a', 'One Piece', owner: FollowedEntryFactory::owner('owner-a'));
        $second = FollowedEntryFactory::entry('entry-b', 'One Piece', owner: FollowedEntryFactory::owner('owner-b'));

        $this->collector->collect($items, $first, 'One Piece');
        $this->collector->collect($items, $second, 'One Piece');

        $this->assertCount(1, $this->articleRepository->articlesOf('entry-a'));
        $this->assertCount(1, $this->articleRepository->articlesOf('entry-b'));
        $this->assertSame('owner-b', $this->articleRepository->articlesOf('entry-b')[0]->owner?->id);
    }

    public function testCutsALongTitleAndCentresTheSnippetOnTheWork(): void
    {
        $longDescription = str_repeat('Lorem ipsum dolor sit amet. ', 10) . 'Le retour de One Piece est prévu. '
            . str_repeat('Consectetur adipiscing elit. ', 10);

        $this->collector->collect(
            [$this->item('One Piece ' . str_repeat('x', 600), 'https://news.example/a', $longDescription)],
            FollowedEntryFactory::entry('entry-a', 'One Piece'),
            'One Piece',
        );

        $article = $this->articleRepository->articles[0];
        $this->assertSame(500, mb_strlen($article->title));
        $this->assertNotNull($article->snippet);
        $this->assertStringStartsWith('…', $article->snippet);
        $this->assertStringEndsWith('…', $article->snippet);
        $this->assertStringContainsString('One Piece', $article->snippet);
    }

    /** The matcher's title words are folded: an accented title still centres its snippet. */
    #[DataProvider('accentedTitles')]
    public function testCentresTheSnippetOnAnAccentedTitle(string $mangaTitle, string $mention): void
    {
        $description = str_repeat('Lorem ipsum dolor sit amet. ', 10) . 'Le retour de ' . $mention . ' est prévu. '
            . str_repeat('Consectetur adipiscing elit. ', 10);

        $this->collector->collect(
            [$this->item('Les sorties : ' . $mention, 'https://news.example/a', $description)],
            FollowedEntryFactory::entry('entry-a', $mangaTitle),
            $mangaTitle,
        );

        $snippet = (string) $this->articleRepository->articles[0]->snippet;
        $this->assertStringStartsWith('…', $snippet);
        $this->assertStringContainsString('Le retour de ' . $mention . ' est prévu.', $snippet);
    }

    /** @return iterable<string, array{string, string}> */
    public static function accentedTitles(): iterable
    {
        yield 'accent in the text and the title' => ['Pokémon', 'Pokémon'];
        yield 'accent in the text only'          => ['Pokemon', 'Pokémon'];
        yield 'capitalised accent'               => ['Élan', 'ÉLAN'];
    }

    public function testANoMentionInTheDescriptionKeepsItsStart(): void
    {
        $description = str_repeat('Lorem ipsum dolor sit amet. ', 10);

        $this->collector->collect(
            [$this->item('One Piece 110', 'https://news.example/a', $description)],
            FollowedEntryFactory::entry('entry-a', 'One Piece'),
            'One Piece',
        );

        $this->assertSame(mb_substr($description, 0, 200), $this->articleRepository->articles[0]->snippet);
    }

    public function testAnEmptyDescriptionHasNoSnippet(): void
    {
        $this->collector->collect(
            [$this->item('One Piece 110', 'https://news.example/a', '')],
            FollowedEntryFactory::entry('entry-a', 'One Piece'),
            'One Piece',
        );

        $this->assertNull($this->articleRepository->articles[0]->snippet);
    }

    private function item(
        string $title,
        string $url,
        string $description = '',
        ?DateTimeImmutable $publishedAt = new DateTimeImmutable('2026-05-01 10:00:00'),
    ): RssFeedItem {
        return new RssFeedItem($title, $description, $url, $publishedAt, 'https://news.example/cover.jpg');
    }
}
