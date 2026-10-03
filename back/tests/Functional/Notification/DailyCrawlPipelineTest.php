<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notification;

use App\Auth\Domain\User;
use App\Collection\Domain\CollectionEntry;
use App\Manga\Domain\Manga;
use App\Notification\Application\Discord\SendSchedulerDiscordSummaryHandler;
use App\Notification\Application\Discord\SendSchedulerDiscordSummaryMessage;
use App\Notification\Application\Fetch\FetchJikanNewsHandler;
use App\Notification\Application\Fetch\FetchJikanNewsMessage;
use App\Notification\Application\Fetch\FetchRssFeedHandler;
use App\Notification\Application\Fetch\FetchRssFeedMessage;
use App\Notification\Application\Schedule\DispatchFollowingCrawlTask;
use App\Notification\Domain\Article;
use App\Notification\Domain\ArticleRepositoryInterface;
use App\Notification\Domain\DiscordNotifierInterface;
use App\Notification\Domain\Service\JikanNewsItem;
use App\Notification\Domain\Service\RssFeedItem;
use App\Notification\Infrastructure\Messenger\CrawlJobCompletionListener;
use App\Tests\Doubles\Notification\InMemoryJikanNewsClient;
use App\Tests\Doubles\Notification\InMemoryRssFeedParser;
use App\Tests\Functional\Fixtures\UserFixtureFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

/**
 * The whole daily crawl against PostgreSQL: the scheduled task plans one job per feed
 * and per MyAnimeList series, each source is downloaded once, its items are linked to
 * every followed series that they name — each account's own copy, owned by it — and
 * the last job of the run asks for the Discord summary.
 */
final class DailyCrawlPipelineTest extends KernelTestCase
{
    private const string MANGA_NEWS_FEED = 'https://www.manga-news.com/index.php/feed/news';

    private EntityManagerInterface $entityManager;
    private InMemoryRssFeedParser $feedParser;
    private InMemoryJikanNewsClient $jikanNewsClient;

    private CollectionEntry $firstOnePiece;
    private CollectionEntry $secondOnePiece;
    private CollectionEntry $naruto;
    private CollectionEntry $unfollowedBerserk;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager       = $container->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;
        /** @var InMemoryRssFeedParser $feedParser */
        $feedParser       = $container->get(InMemoryRssFeedParser::class);
        $this->feedParser = $feedParser;
        /** @var InMemoryJikanNewsClient $jikanNewsClient */
        $jikanNewsClient       = $container->get(InMemoryJikanNewsClient::class);
        $this->jikanNewsClient = $jikanNewsClient;

        $firstOwner  = UserFixtureFactory::createActiveUser($container, email: 'first@test.local');
        $secondOwner = UserFixtureFactory::createActiveUser($container, email: 'second@test.local');

        // Each account follows its own copy of One Piece.
        $this->firstOnePiece     = $this->followedEntry($firstOwner, 'One Piece', '13');
        $this->secondOnePiece    = $this->followedEntry($secondOwner, 'One Piece', '13');
        $this->naruto            = $this->followedEntry($firstOwner, 'Naruto', null);
        $this->unfollowedBerserk = $this->followedEntry($secondOwner, 'Berserk', null, followed: false);
        $this->entityManager->flush();

        $this->feedParser->serve(self::MANGA_NEWS_FEED, [
            $this->feedItem('One Piece : le tome 110 en mai', 'https://www.manga-news.com/one-piece-110'),
            $this->feedItem('Naruto : nouvelle édition', 'https://www.manga-news.com/naruto'),
            $this->feedItem('Berserk : la suite', 'https://www.manga-news.com/berserk'),
        ]);
        $this->jikanNewsClient->serve('13', [
            new JikanNewsItem('https://myanimelist.net/news/1', 'One Piece anime returns', '', 'mal', '2026-05-01T10:00:00+00:00'),
        ]);
    }

    public function testEachSourceIsDownloadedOncePerRun(): void
    {
        $jobs = $this->dispatchTheCrawl();

        $this->assertCount(7, array_filter($jobs, static fn (object $job): bool => $job instanceof FetchRssFeedMessage));
        $jikanJobs = array_values(array_filter($jobs, static fn (object $job): bool => $job instanceof FetchJikanNewsMessage));
        $this->assertCount(1, $jikanJobs);
        $this->assertCount(2, $jikanJobs[0]->followedSeries);

        $this->runTheJobs($jobs);

        /** @var list<array{name: string, url: string}> $feeds */
        $feeds = static::getContainer()->getParameter('following.rss_feeds');
        foreach ($feeds as $feed) {
            $this->assertSame(1, $this->feedParser->downloadsOf($feed['url']), $feed['name']);
        }
        $this->assertSame(1, $this->jikanNewsClient->downloadsOf('13'));
    }

    public function testEveryFollowedSeriesGetsTheArticlesThatNameIt(): void
    {
        $this->runTheJobs($this->dispatchTheCrawl());

        $expectedOnePieceUrls = ['https://myanimelist.net/news/1', 'https://www.manga-news.com/one-piece-110'];
        $this->assertSame($expectedOnePieceUrls, $this->urlsOf($this->firstOnePiece));
        $this->assertSame($expectedOnePieceUrls, $this->urlsOf($this->secondOnePiece));
        $this->assertSame(['https://www.manga-news.com/naruto'], $this->urlsOf($this->naruto));
        $this->assertSame([], $this->urlsOf($this->unfollowedBerserk));

        // Each account owns the articles of its own copy.
        foreach ($this->articlesOf($this->secondOnePiece) as $article) {
            $this->assertSame($this->secondOnePiece->owner?->id, $article->owner?->id);
        }
    }

    public function testRunningTheSameJobsAgainLinksNothingTwice(): void
    {
        $jobs = $this->dispatchTheCrawl();
        $this->runTheJobs($jobs);
        $this->runTheJobs($jobs);

        $this->assertCount(2, $this->articlesOf($this->firstOnePiece));
    }

    public function testTheLastJobOfTheRunSendsTheDiscordSummary(): void
    {
        $runStartedAt = new DateTimeImmutable('-1 second');
        $summaryRequests = $this->runTheJobs($this->dispatchTheCrawl());

        $this->assertCount(1, $summaryRequests);
        $this->assertGreaterThanOrEqual($runStartedAt->getTimestamp() - 1, $summaryRequests[0]->scheduledAt->getTimestamp());

        $summaries = [];
        $discord   = $this->createStub(DiscordNotifierInterface::class);
        $discord->method('sendSchedulerSummary')->willReturnCallback(
            static function (array $entries) use (&$summaries): void {
                $summaries[] = $entries;
            },
        );
        /** @var ArticleRepositoryInterface $articleRepository */
        $articleRepository = static::getContainer()->get(ArticleRepositoryInterface::class);

        (new SendSchedulerDiscordSummaryHandler($articleRepository, $discord))($summaryRequests[0]);

        $this->assertCount(1, $summaries);
        $this->assertSame(['Naruto', 'One Piece'], array_column($summaries[0], 'mangaTitle'));
        $this->assertCount(1, $summaries[0][0]['articles']);
        // Both copies' articles: the summary is one message for the whole run.
        $this->assertCount(4, $summaries[0][1]['articles']);
    }

    /** @return list<object> the jobs the scheduled task sent to the worker */
    private function dispatchTheCrawl(): array
    {
        /** @var DispatchFollowingCrawlTask $task */
        $task = static::getContainer()->get(DispatchFollowingCrawlTask::class);
        $task();

        return array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $this->asyncTransport()->getSent());
    }

    /**
     * Handles the jobs as the worker does, each completion counted towards its run.
     *
     * @param  list<object> $jobs
     * @return list<SendSchedulerDiscordSummaryMessage> the summaries asked for
     */
    private function runTheJobs(array $jobs): array
    {
        $container = static::getContainer();
        /** @var FetchRssFeedHandler $rssHandler */
        $rssHandler = $container->get(FetchRssFeedHandler::class);
        /** @var FetchJikanNewsHandler $jikanHandler */
        $jikanHandler = $container->get(FetchJikanNewsHandler::class);
        /** @var CrawlJobCompletionListener $completionListener */
        $completionListener = $container->get(CrawlJobCompletionListener::class);

        $this->asyncTransport()->reset();

        foreach ($jobs as $job) {
            match (true) {
                $job instanceof FetchRssFeedMessage   => $rssHandler($job),
                $job instanceof FetchJikanNewsMessage => $jikanHandler($job),
                default                               => null,
            };
            $completionListener->onHandled(new WorkerMessageHandledEvent(new Envelope($job), 'async'));
        }

        return array_values(array_filter(
            array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $this->asyncTransport()->getSent()),
            static fn (object $message): bool => $message instanceof SendSchedulerDiscordSummaryMessage,
        ));
    }

    private function asyncTransport(): InMemoryTransport
    {
        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');

        return $transport;
    }

    private function followedEntry(User $owner, string $title, ?string $malId, bool $followed = true): CollectionEntry
    {
        $manga = new Manga(
            id: Uuid::v4()->toRfc4122(),
            title: $title,
            edition: 'Glénat',
            language: 'fr',
            externalId: $malId,
            owner: $owner,
        );
        $entry = new CollectionEntry(
            id: Uuid::v4()->toRfc4122(),
            manga: $manga,
            owner: $owner,
            notificationsEnabled: $followed,
        );

        $this->entityManager->persist($manga);
        $this->entityManager->persist($entry);

        return $entry;
    }

    private function feedItem(string $title, string $url): RssFeedItem
    {
        return new RssFeedItem($title, '', $url, new DateTimeImmutable('2026-05-01 10:00:00'), null);
    }

    /** @return list<Article> */
    private function articlesOf(CollectionEntry $entry): array
    {
        /** @var list<Article> $articles */
        $articles = $this->entityManager->getRepository(Article::class)->findBy(
            ['collectionEntry' => $entry],
            ['url' => 'ASC'],
        );

        return $articles;
    }

    /** @return list<string> */
    private function urlsOf(CollectionEntry $entry): array
    {
        return array_map(static fn (Article $article): string => $article->url, $this->articlesOf($entry));
    }
}
