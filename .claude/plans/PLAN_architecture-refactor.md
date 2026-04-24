# Architecture Refactor — R1–R6 + ActivityLog Kernel Subscriber

**Date:** 2026-04-24
**Scope:** ALL modules under `back/src/`
**Rules enforced:** R1 (domain events for all side effects), R2 (Shared SDK per module),
R3 (thin handlers), R4 (inject domain interfaces), R5 (one event many listeners),
R6 (inter-module deps via `{Module}_Shared` only, enforced by Deptrac)

---

## Pre-flight decisions

### Event bus transport
`messenger.yaml` routes fetch messages to `async`. Domain events dispatched from handlers
flow through `event.bus` which has NO explicit routing → synchronous by default. This is
correct: ActivityLog must be written before the job ends. **Do NOT add async routing for
domain events.**

### Correlation ID strategy
`*StartedEvent` generates a UUID (`correlationId`) in its constructor. That same UUID is
used as the `ActivityLog::$id`. `*SucceededEvent` / `*FailedEvent` carry that UUID so
listeners call `findById($correlationId)` to load and update the log.
**No schema change needed** — the existing `id` column already holds a UUID.

---

## Phase 1 — Domain Event classes

### New directory: `back/src/Notification/Shared/Event/`

#### `RssFetchStartedEvent`
```php
// back/src/Notification/Shared/Event/RssFetchStartedEvent.php
declare(strict_types=1);
namespace App\Notification\Shared\Event;
use Symfony\Component\Uid\Uuid;

final readonly class RssFetchStartedEvent
{
    public string $correlationId;

    public function __construct(
        public string $feedName,
        public string $feedUrl,
        public string $mangaTitle,
        public string $collectionEntryId,
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? Uuid::v4()->toRfc4122();
    }
}
```

#### `RssFetchSucceededEvent`
```php
// back/src/Notification/Shared/Event/RssFetchSucceededEvent.php
declare(strict_types=1);
namespace App\Notification\Shared\Event;

final readonly class RssFetchSucceededEvent
{
    public function __construct(
        public string $correlationId,
        public string $feedName,
        public string $collectionEntryId,
        public int    $newCount,
        public int    $itemsScanned,
        public string $mangaTitle,
        public ?string $mangaCoverUrl,
    ) {}
}
```

#### `RssFetchFailedEvent`
```php
// back/src/Notification/Shared/Event/RssFetchFailedEvent.php
declare(strict_types=1);
namespace App\Notification\Shared\Event;

final readonly class RssFetchFailedEvent
{
    public function __construct(
        public string $correlationId,
        public string $feedName,
        public string $collectionEntryId,
        public string $error,
        public string $exceptionClass,
    ) {}
}
```

#### `JikanFetchStartedEvent`
```php
// back/src/Notification/Shared/Event/JikanFetchStartedEvent.php
declare(strict_types=1);
namespace App\Notification\Shared\Event;
use Symfony\Component\Uid\Uuid;

final readonly class JikanFetchStartedEvent
{
    public string $correlationId;

    public function __construct(
        public string $malId,
        public string $mangaTitle,
        public string $collectionEntryId,
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? Uuid::v4()->toRfc4122();
    }
}
```

#### `JikanFetchSucceededEvent`
```php
// back/src/Notification/Shared/Event/JikanFetchSucceededEvent.php
declare(strict_types=1);
namespace App\Notification\Shared\Event;

final readonly class JikanFetchSucceededEvent
{
    public function __construct(
        public string $correlationId,
        public string $malId,
        public string $collectionEntryId,
        public int    $newCount,
        public int    $itemsReceived,
        public string $mangaTitle,
        public ?string $mangaCoverUrl,
    ) {}
}
```

#### `JikanFetchFailedEvent`
```php
// back/src/Notification/Shared/Event/JikanFetchFailedEvent.php
declare(strict_types=1);
namespace App\Notification\Shared\Event;

final readonly class JikanFetchFailedEvent
{
    public function __construct(
        public string $correlationId,
        public string $malId,
        public string $collectionEntryId,
        public string $error,
        public string $exceptionClass,
    ) {}
}
```

#### `DiscordNotificationSentEvent`
```php
// back/src/Notification/Shared/Event/DiscordNotificationSentEvent.php
declare(strict_types=1);
namespace App\Notification\Shared\Event;

final readonly class DiscordNotificationSentEvent
{
    /**
     * @param array<int, array{title: string, url: string, sourceName: string}> $articles
     */
    public function __construct(
        public string  $correlationId,
        public string  $collectionEntryId,
        public string  $mangaTitle,
        public ?string $mangaCoverUrl,
        public int     $articleCount,
        public array   $articles,
    ) {}
}
```

#### `DiscordNotificationSkippedEvent`
```php
// back/src/Notification/Shared/Event/DiscordNotificationSkippedEvent.php
declare(strict_types=1);
namespace App\Notification\Shared\Event;

final readonly class DiscordNotificationSkippedEvent
{
    public function __construct(
        public string $collectionEntryId,
        public string $mangaTitle,
        public string $reason, // 'cooldown' | 'no_articles'
    ) {}
}
```

#### `SchedulerFiredEvent`
```php
// back/src/Notification/Shared/Event/SchedulerFiredEvent.php
declare(strict_types=1);
namespace App\Notification\Shared\Event;
use Symfony\Component\Uid\Uuid;

final readonly class SchedulerFiredEvent
{
    public string $correlationId;

    public function __construct(
        public int $followedCount,
        public int $jobsDispatched,
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? Uuid::v4()->toRfc4122();
    }
}
```

---

## Phase 2 — `Shared\Event\UserActionEvent`

```php
// back/src/Shared/Event/UserActionEvent.php
declare(strict_types=1);
namespace App\Shared\Event;

final readonly class UserActionEvent
{
    public function __construct(
        public string $method,
        public string $path,
        public int    $statusCode,
        public string $routeName,
        public int    $durationMs,
    ) {}
}
```

---

## Phase 3 — `ActivityLogRepositoryInterface` + `DoctrineActivityLogRepository`

Add `findById()` to the interface:
```php
// back/src/Notification/Domain/ActivityLogRepositoryInterface.php
public function findById(string $id): ?ActivityLog;
```

Implement in the Doctrine repo:
```php
// back/src/Notification/Infrastructure/Doctrine/DoctrineActivityLogRepository.php
public function findById(string $id): ?ActivityLog
{
    return $this->em()->find(ActivityLog::class, $id);
}
```

No migration needed — `id` is already the PK UUID column.

---

## Phase 4 — Domain ports (interfaces) + Infrastructure adapters

Both RSS and Jikan involve HTTP calls → Infrastructure. The Domain defines the **port**
(interface + DTOs), the Infrastructure implements the **adapter**.
Handlers inject the interface (R4). Zero HTTP logic in Domain.

### 4.1 DTOs (Domain — pure value objects, no logic)

```php
// back/src/Notification/Domain/Service/RssFetchResult.php
declare(strict_types=1);
namespace App\Notification\Domain\Service;

final readonly class RssFetchResult
{
    public function __construct(
        public int $newCount,
        public int $itemsScanned,
    ) {}
}
```

```php
// back/src/Notification/Domain/Service/JikanFetchResult.php
declare(strict_types=1);
namespace App\Notification\Domain\Service;

final readonly class JikanFetchResult
{
    public function __construct(
        public int $newCount,
        public int $itemsReceived,
    ) {}
}
```

### 4.2 Domain interfaces (ports)

```php
// back/src/Notification/Domain/Service/RssFeedParserInterface.php
declare(strict_types=1);
namespace App\Notification\Domain\Service;

use App\Collection\Domain\CollectionEntry;

interface RssFeedParserInterface
{
    public function parse(string $feedUrl, string $mangaTitle, CollectionEntry $entry): RssFetchResult;
}
```

```php
// back/src/Notification/Domain/Service/JikanNewsClientInterface.php
declare(strict_types=1);
namespace App\Notification\Domain\Service;

use App\Collection\Domain\CollectionEntry;

interface JikanNewsClientInterface
{
    public function fetch(string $malId, CollectionEntry $entry): JikanFetchResult;
}
```

### 4.3 `RssFeedParserException` (Domain — exception type, no infra)

```php
// back/src/Notification/Domain/Service/RssFeedParserException.php
declare(strict_types=1);
namespace App\Notification\Domain\Service;
use RuntimeException;

final class RssFeedParserException extends RuntimeException
{
    public static function httpError(int $statusCode): self
    {
        return new self("HTTP {$statusCode}");
    }

    public static function invalidXml(string $url): self
    {
        return new self("Invalid XML from {$url}");
    }
}
```

### 4.4 Infrastructure adapter: `RssFeedParser`

```php
// back/src/Notification/Infrastructure/Rss/RssFeedParser.php
declare(strict_types=1);
namespace App\Notification\Infrastructure\Rss;

use App\Collection\Domain\CollectionEntry;
use App\Notification\Domain\Article;
use App\Notification\Domain\ArticleRepositoryInterface;
use App\Notification\Domain\Service\RssFeedParserException;
use App\Notification\Domain\Service\RssFeedParserInterface;
use App\Notification\Domain\Service\RssFetchResult;
use DateTimeImmutable;
use DateTimeInterface;
use SimpleXMLElement;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class RssFeedParser implements RssFeedParserInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ArticleRepositoryInterface $articleRepository,
    ) {}

    public function parse(string $feedUrl, string $mangaTitle, CollectionEntry $entry): RssFetchResult
    {
        $response   = $this->httpClient->request('GET', $feedUrl, [
            'timeout' => 10,
            'headers' => ['User-Agent' => 'Ziggytheque/1.0 (manga tracker)'],
        ]);
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            throw RssFeedParserException::httpError($statusCode);
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->getContent(false));
        libxml_clear_errors();

        if ($xml === false) {
            throw RssFeedParserException::invalidXml($feedUrl);
        }

        return $this->processItems($xml, $mangaTitle, $entry);
    }

    private function processItems(SimpleXMLElement $xml, string $mangaTitle, CollectionEntry $entry): RssFetchResult
    {
        $newCount        = 0;
        $itemsScanned    = 0;
        $normalizedTitle = mb_strtolower($mangaTitle);
        $keywords        = array_filter(
            array_map('trim', explode(' ', $normalizedTitle)),
            static fn (string $k) => mb_strlen($k) >= 3,
        );
        $channel = $xml->channel ?? $xml;

        foreach ($channel->item ?? [] as $item) {
            ++$itemsScanned;

            $itemTitle = html_entity_decode((string) ($item->title ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $itemDesc  = html_entity_decode(
                strip_tags((string) ($item->description ?? '')),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            );
            $itemUrl  = (string) ($item->link ?? $item->guid ?? '');
            $itemDate = (string) ($item->pubDate ?? $item->children('dc', true)->date ?? '');

            $haystack   = mb_strtolower($itemTitle . ' ' . $itemDesc);
            $titleMatch = str_contains($haystack, $normalizedTitle);
            $matches    = $titleMatch
                ? [$normalizedTitle]
                : array_filter($keywords, static fn (string $k) => str_contains($haystack, $k));

            if (empty($matches) || $itemUrl === '') {
                continue;
            }

            $publishedAt = $itemDate !== ''
                ? DateTimeImmutable::createFromFormat(DateTimeInterface::RSS, $itemDate) ?: null
                : null;

            if ($publishedAt !== null && $publishedAt < new DateTimeImmutable('2026-04-01')) {
                continue;
            }

            if ($this->articleRepository->existsByCollectionEntryAndUrl($entry->id, $itemUrl)) {
                continue;
            }

            $article = new Article(
                id: Uuid::v4()->toRfc4122(),
                collectionEntry: $entry,
                title: mb_substr($itemTitle, 0, 500),
                url: $itemUrl,
                sourceName: 'rss',
                author: null,
                imageUrl: $this->extractImage($item),
                publishedAt: $publishedAt ?: null,
                snippet: $this->extractSnippet($itemDesc, array_values($matches)),
            );
            $this->articleRepository->save($article);
            ++$newCount;
        }

        return new RssFetchResult($newCount, $itemsScanned);
    }

    /** @param string[] $keywords */
    private function extractSnippet(string $text, array $keywords): ?string
    {
        if ($text === '' || $keywords === []) {
            return null;
        }
        $lower   = mb_strtolower($text);
        $bestPos = PHP_INT_MAX;
        $best    = null;
        foreach ($keywords as $kw) {
            $pos = mb_strpos($lower, $kw);
            if ($pos !== false && $pos < $bestPos) {
                $bestPos = $pos;
                $best    = $kw;
            }
        }
        if ($best === null) {
            return mb_substr($text, 0, 200);
        }
        $start   = max(0, $bestPos - 80);
        $excerpt = mb_substr($text, $start, 220);
        if ($start > 0) {
            $excerpt = '…' . ltrim($excerpt);
        }
        return rtrim($excerpt) . (mb_strlen($text) > $start + 220 ? '…' : '');
    }

    private function extractImage(SimpleXMLElement $item): ?string
    {
        $media = $item->children('media', true);
        if (isset($media->content)) {
            $url = (string) $media->content->attributes()['url'];
            if ($url !== '') {
                return $url;
            }
        }
        if (isset($item->enclosure)) {
            $type = (string) $item->enclosure->attributes()['type'];
            if (str_starts_with($type, 'image/')) {
                return (string) $item->enclosure->attributes()['url'];
            }
        }
        $desc = (string) ($item->description ?? '');
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $desc, $m)) {
            return $m[1];
        }
        return null;
    }
}
```

### 4.5 Infrastructure adapter: `JikanNewsClient`

```php
// back/src/Notification/Infrastructure/Jikan/JikanNewsClient.php
declare(strict_types=1);
namespace App\Notification\Infrastructure\Jikan;

use App\Collection\Domain\CollectionEntry;
use App\Notification\Domain\Article;
use App\Notification\Domain\ArticleRepositoryInterface;
use App\Notification\Domain\Service\JikanFetchResult;
use App\Notification\Domain\Service\JikanNewsClientInterface;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class JikanNewsClient implements JikanNewsClientInterface
{
    private const BASE_URL = 'https://api.jikan.moe/v4';

    public function __construct(
        private HttpClientInterface $httpClient,
        private ArticleRepositoryInterface $articleRepository,
    ) {}

    public function fetch(string $malId, CollectionEntry $entry): JikanFetchResult
    {
        $response = $this->httpClient->request(
            'GET',
            self::BASE_URL . '/manga/' . $malId . '/news',
            ['timeout' => 10],
        );
        $data     = $response->toArray();
        $items    = $data['data'] ?? [];
        $newCount = 0;

        foreach ($items as $item) {
            $url = $item['url'] ?? null;
            if ($url === null) {
                continue;
            }
            if ($this->articleRepository->existsByCollectionEntryAndUrl($entry->id, $url)) {
                continue;
            }
            $publishedAt = isset($item['date']) ? new DateTimeImmutable($item['date']) : null;
            if ($publishedAt !== null && $publishedAt < new DateTimeImmutable('2026-04-01')) {
                continue;
            }
            $article = new Article(
                id: Uuid::v4()->toRfc4122(),
                collectionEntry: $entry,
                title: mb_substr((string) ($item['title'] ?? 'Jikan News'), 0, 500),
                url: $url,
                sourceName: 'jikan-news',
                author: $item['author_username'] ?? null,
                imageUrl: null,
                publishedAt: $publishedAt,
                snippet: isset($item['excerpt']) && $item['excerpt'] !== ''
                    ? mb_substr((string) $item['excerpt'], 0, 500)
                    : null,
            );
            $this->articleRepository->save($article);
            ++$newCount;
        }

        return new JikanFetchResult($newCount, count($items));
    }
}
```

### 4.6 `services.yaml` — wire adapters to interfaces

```yaml
App\Notification\Domain\Service\RssFeedParserInterface:
    alias: App\Notification\Infrastructure\Rss\RssFeedParser

App\Notification\Domain\Service\JikanNewsClientInterface:
    alias: App\Notification\Infrastructure\Jikan\JikanNewsClient
```

---

## Phase 5 — Thin handlers

### `FetchRssFeedHandler` (full rewrite)
```php
// back/src/Notification/Application/Fetch/FetchRssFeedHandler.php
declare(strict_types=1);
namespace App\Notification\Application\Fetch;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\Service\RssFeedParser;
use App\Notification\Shared\Event\RssFetchFailedEvent;
use App\Notification\Shared\Event\RssFetchStartedEvent;
use App\Notification\Shared\Event\RssFetchSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final readonly class FetchRssFeedHandler
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private RssFeedParser $rssFeedParser,
        private EventBusInterface $eventBus,
    ) {}

    public function __invoke(FetchRssFeedMessage $message): void
    {
        $entry = $this->collectionRepository->findById($message->collectionEntryId);
        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $message->collectionEntryId);
        }

        $started = new RssFetchStartedEvent(
            feedName: $message->feedName,
            feedUrl: $message->feedUrl,
            mangaTitle: $message->mangaTitle,
            collectionEntryId: $entry->id,
        );
        $this->eventBus->publish($started);

        try {
            $result = $this->rssFeedParser->parse($message->feedUrl, $message->mangaTitle, $entry);

            $this->eventBus->publish(new RssFetchSucceededEvent(
                correlationId: $started->correlationId,
                feedName: $message->feedName,
                collectionEntryId: $entry->id,
                newCount: $result['newCount'],
                itemsScanned: $result['itemsScanned'],
                mangaTitle: $entry->manga->title,
                mangaCoverUrl: $entry->manga->coverUrl,
            ));
        } catch (Throwable $e) {
            $this->eventBus->publish(new RssFetchFailedEvent(
                correlationId: $started->correlationId,
                feedName: $message->feedName,
                collectionEntryId: $entry->id,
                error: $e->getMessage(),
                exceptionClass: $e::class,
            ));
            throw $e;
        }
    }
}
```

### `FetchJikanNewsHandler` (full rewrite)
```php
// back/src/Notification/Application/Fetch/FetchJikanNewsHandler.php
declare(strict_types=1);
namespace App\Notification\Application\Fetch;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\Service\JikanNewsFetcher;
use App\Notification\Shared\Event\JikanFetchFailedEvent;
use App\Notification\Shared\Event\JikanFetchStartedEvent;
use App\Notification\Shared\Event\JikanFetchSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final readonly class FetchJikanNewsHandler
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private JikanNewsFetcher $jikanNewsFetcher,
        private EventBusInterface $eventBus,
    ) {}

    public function __invoke(FetchJikanNewsMessage $message): void
    {
        $entry = $this->collectionRepository->findById($message->collectionEntryId);
        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $message->collectionEntryId);
        }

        $started = new JikanFetchStartedEvent(
            malId: $message->malId,
            mangaTitle: $message->mangaTitle,
            collectionEntryId: $entry->id,
        );
        $this->eventBus->publish($started);

        try {
            $result = $this->jikanNewsFetcher->fetch($message->malId, $entry);

            $this->eventBus->publish(new JikanFetchSucceededEvent(
                correlationId: $started->correlationId,
                malId: $message->malId,
                collectionEntryId: $entry->id,
                newCount: $result['newCount'],
                itemsReceived: $result['itemsReceived'],
                mangaTitle: $entry->manga->title,
                mangaCoverUrl: $entry->manga->coverUrl,
            ));
        } catch (Throwable $e) {
            $this->eventBus->publish(new JikanFetchFailedEvent(
                correlationId: $started->correlationId,
                malId: $message->malId,
                collectionEntryId: $entry->id,
                error: $e->getMessage(),
                exceptionClass: $e::class,
            ));
            throw $e;
        }
    }
}
```

### `SendDiscordNotificationHandler` (full rewrite)
```php
// back/src/Notification/Application/Discord/SendDiscordNotificationHandler.php
declare(strict_types=1);
namespace App\Notification\Application\Discord;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\ArticleRepositoryInterface;
use App\Notification\Shared\Event\DiscordNotificationSentEvent;
use App\Notification\Shared\Event\DiscordNotificationSkippedEvent;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class SendDiscordNotificationHandler
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private ArticleRepositoryInterface $articleRepository,
        private EventBusInterface $eventBus,
    ) {}

    public function __invoke(SendDiscordNotificationMessage $message): void
    {
        $entry = $this->collectionRepository->findById($message->collectionEntryId);
        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $message->collectionEntryId);
        }

        if ($entry->lastNotifiedAt !== null) {
            $this->eventBus->publish(new DiscordNotificationSkippedEvent(
                collectionEntryId: $entry->id,
                mangaTitle: $entry->manga->title,
                reason: 'cooldown',
            ));
            return;
        }

        $result   = $this->articleRepository->findPaginated(1, 10, $entry->id);
        $articles = $result['items'];

        if ($articles === []) {
            $this->eventBus->publish(new DiscordNotificationSkippedEvent(
                collectionEntryId: $entry->id,
                mangaTitle: $entry->manga->title,
                reason: 'no_articles',
            ));
            return;
        }

        $entry->lastNotifiedAt = new DateTimeImmutable();
        $this->collectionRepository->save($entry);

        $this->eventBus->publish(new DiscordNotificationSentEvent(
            correlationId: Uuid::v4()->toRfc4122(),
            collectionEntryId: $entry->id,
            mangaTitle: $entry->manga->title,
            mangaCoverUrl: $entry->manga->coverUrl,
            articleCount: count($articles),
            articles: array_map(static fn ($a) => $a->toArray(), $articles),
        ));
    }
}
```

### `DispatchFollowingCrawlTask` (remove activityLog, add event)
```php
// back/src/Notification/Application/Schedule/DispatchFollowingCrawlTask.php
// CHANGE: remove $activityLogRepository injection, add $eventBus
// Add at end of __invoke():
$this->eventBus->publish(new SchedulerFiredEvent(
    followedCount: count($followed),
    jobsDispatched: $totalJobs,
));
```

---

## Phase 6 — Infrastructure Listeners

All in `back/src/Notification/Infrastructure/Listener/`, all `final readonly`, `#[AsEventListener]`.

### `ActivityLogRssFetchStartedListener`
```php
declare(strict_types=1);
namespace App\Notification\Infrastructure\Listener;

use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Domain\EventTypeEnum;
use App\Notification\Shared\Event\RssFetchStartedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class ActivityLogRssFetchStartedListener
{
    public function __construct(private ActivityLogRepositoryInterface $activityLogRepository) {}

    public function __invoke(RssFetchStartedEvent $event): void
    {
        $log = new ActivityLog(
            id: $event->correlationId,
            eventType: EventTypeEnum::RssFetch,
            sourceName: $event->feedName,
            metadata: ['feed_url' => $event->feedUrl, 'manga' => $event->mangaTitle],
        );
        $this->activityLogRepository->save($log);
    }
}
```

### `ActivityLogRssFetchSucceededListener`
```php
#[AsEventListener]
final readonly class ActivityLogRssFetchSucceededListener
{
    public function __construct(private ActivityLogRepositoryInterface $activityLogRepository) {}

    public function __invoke(RssFetchSucceededEvent $event): void
    {
        $log = $this->activityLogRepository->findById($event->correlationId);
        if ($log === null) { return; }
        $log->markSuccess($event->newCount, ['items_scanned' => $event->itemsScanned]);
        $this->activityLogRepository->save($log);
    }
}
```

### `ActivityLogRssFetchFailedListener`
```php
#[AsEventListener]
final readonly class ActivityLogRssFetchFailedListener
{
    public function __construct(private ActivityLogRepositoryInterface $activityLogRepository) {}

    public function __invoke(RssFetchFailedEvent $event): void
    {
        $log = $this->activityLogRepository->findById($event->correlationId);
        if ($log === null) { return; }
        $log->markError($event->error, ['exception_class' => $event->exceptionClass]);
        $this->activityLogRepository->save($log);
    }
}
```

### (Same pattern × 3 for Jikan: `ActivityLogJikanFetchStartedListener`, `...SucceededListener`, `...FailedListener`)

### `ActivityLogDiscordSentListener`
```php
#[AsEventListener]
final readonly class ActivityLogDiscordSentListener
{
    public function __construct(private ActivityLogRepositoryInterface $activityLogRepository) {}

    public function __invoke(DiscordNotificationSentEvent $event): void
    {
        $log = new ActivityLog(
            id: $event->correlationId,
            eventType: EventTypeEnum::DiscordSent,
            sourceName: 'discord',
        );
        $log->markSuccess($event->articleCount, ['articleCount' => $event->articleCount]);
        $this->activityLogRepository->save($log);
    }
}
```

### `ActivityLogSchedulerFiredListener`
```php
#[AsEventListener]
final readonly class ActivityLogSchedulerFiredListener
{
    public function __construct(private ActivityLogRepositoryInterface $activityLogRepository) {}

    public function __invoke(SchedulerFiredEvent $event): void
    {
        $log = new ActivityLog(
            id: $event->correlationId,
            eventType: EventTypeEnum::SchedulerFire,
            sourceName: 'scheduler',
        );
        $log->markSuccess(0, [
            'followed'        => $event->followedCount,
            'jobs_dispatched' => $event->jobsDispatched,
        ]);
        $this->activityLogRepository->save($log);
    }
}
```

### `DiscordRssFetchSucceededListener`
```php
#[AsEventListener]
final readonly class DiscordRssFetchSucceededListener
{
    public function __construct(private MessageBusInterface $messageBus) {}

    public function __invoke(RssFetchSucceededEvent $event): void
    {
        if ($event->newCount === 0) { return; }
        $this->messageBus->dispatch(new SendDiscordNotificationMessage(
            collectionEntryId: $event->collectionEntryId,
            articleIds: [],
        ));
    }
}
```

### `DiscordJikanFetchSucceededListener` (same pattern, listens to `JikanFetchSucceededEvent`)

### `DiscordNotificationSentListener`
```php
#[AsEventListener]
final readonly class DiscordNotificationSentListener
{
    public function __construct(private DiscordNotifierInterface $discord) {}

    public function __invoke(DiscordNotificationSentEvent $event): void
    {
        $this->discord->sendNewArticles(
            mangaTitle: $event->mangaTitle,
            mangaCoverUrl: $event->mangaCoverUrl,
            count: $event->articleCount,
            articles: $event->articles,
        );
    }
}
```

### `ActivityLogUserActionListener`
```php
// back/src/Shared/Infrastructure/Http/ActivityLogUserActionListener.php
declare(strict_types=1);
namespace App\Shared\Infrastructure\Http;

use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Domain\EventTypeEnum;
use App\Shared\Event\UserActionEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Uid\Uuid;

#[AsEventListener]
final readonly class ActivityLogUserActionListener
{
    public function __construct(private ActivityLogRepositoryInterface $activityLogRepository) {}

    public function __invoke(UserActionEvent $event): void
    {
        $log = new ActivityLog(
            id: Uuid::v4()->toRfc4122(),
            eventType: EventTypeEnum::UserAction,
            sourceName: 'http',
            metadata: [
                'method'      => $event->method,
                'path'        => $event->path,
                'status_code' => $event->statusCode,
                'route'       => $event->routeName,
                'duration_ms' => $event->durationMs,
            ],
        );
        $log->markSuccess();
        $this->activityLogRepository->save($log);
    }
}
```

---

## Phase 7 — Kernel EventSubscriber

```php
// back/src/Shared/Infrastructure/Http/ActivityLogKernelSubscriber.php
declare(strict_types=1);
namespace App\Shared\Infrastructure\Http;

use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Event\UserActionEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class ActivityLogKernelSubscriber
{
    private const EXCLUDED_PREFIXES = ['/api/auth'];

    public function __construct(private EventBusInterface $eventBus) {}

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path    = $request->getPathInfo();

        if (!str_starts_with($path, '/api/')) {
            return;
        }

        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $startTime  = $request->server->get('REQUEST_TIME_FLOAT') ?? microtime(true);
        $durationMs = (int) ((microtime(true) - (float) $startTime) * 1000);

        $this->eventBus->publish(new UserActionEvent(
            method: $request->getMethod(),
            path: $path,
            statusCode: $event->getResponse()->getStatusCode(),
            routeName: (string) ($request->attributes->get('_route') ?? ''),
            durationMs: $durationMs,
        ));
    }
}
```

---

## Phase 8 — `WorkerFailureSubscriber` update

Remove `LoggerInterface` injection. Keep `ActivityLogRepositoryInterface` + `DiscordNotifierInterface`.
ActivityLog is already written directly here (worker failure is a special infra concern).

---

## Phase 9 — `services.yaml` additions

```yaml
# Remove activityLogRepository from DispatchFollowingCrawlTask args (autowired RssFeedParser replaces it)
App\Notification\Application\Schedule\DispatchFollowingCrawlTask:
    arguments:
        $rssFeeds: '%following.rss_feeds%'
        # remove: $activityLogRepository

# Domain services (autowired automatically, listed for clarity)
# App\Notification\Domain\Service\RssFeedParser: ~
# App\Notification\Domain\Service\JikanNewsFetcher: ~

# All listeners are autoconfigured via #[AsEventListener] — no explicit services.yaml needed
```

---

## Phase 10 — `deptrac.yaml` update

Add new layer:
```yaml
- name: Notification_Shared
  collectors:
    - type: class
      value: 'App\\Notification\\Shared.*'
```

Update ruleset:
```yaml
Notification_Shared:
  - Shared

Notification_Application:
  - Notification_Domain
  - Notification_Shared   # handlers dispatch Shared events
  - Collection_Domain
  - Shared

Notification_Infrastructure:
  - Notification_Domain
  - Notification_Application
  - Notification_Shared   # listeners subscribe to Shared events
  - Shared
```

Also add `Notification_Shared` to the `Shared` layer dependencies of any layer that
needs to listen to Notification events (e.g., `Shared_Infrastructure` for UserAction listener).

Since `ActivityLogUserActionListener` and `ActivityLogKernelSubscriber` live in
`App\Shared\Infrastructure\Http\` and depend on `App\Notification\Domain\*`,
add `Notification_Domain` to Shared Infrastructure. OR move them to
`App\Notification\Infrastructure\Http\` to keep Shared clean.

**Recommended:** Move both to `App\Notification\Infrastructure\Http\` so Shared stays
truly dependency-free.

---

## Phase 11 — No migration needed

`ActivityLog::$id` is already a UUID PK. The `correlationId` is the same UUID generated
in `*StartedEvent` and used as the `ActivityLog::$id`. Zero schema change.

---

## Sequencing (strict order)

1. Create all `Notification\Shared\Event\*` value objects
2. Create `Shared\Event\UserActionEvent`
3. Add `findById()` to `ActivityLogRepositoryInterface` + `DoctrineActivityLogRepository`
4. Create `RssFeedParserException`, `RssFeedParser`, `JikanNewsFetcher`
5. Rewrite `FetchRssFeedHandler`, `FetchJikanNewsHandler`, `SendDiscordNotificationHandler`
6. Update `DispatchFollowingCrawlTask`
7. Create all Infrastructure listeners (ActivityLog × 8 + Discord × 3)
8. Create `ActivityLogKernelSubscriber` + `ActivityLogUserActionListener` (in `Notification\Infrastructure\Http\`)
9. Update `WorkerFailureSubscriber` (remove LoggerInterface)
10. Update `services.yaml`
11. Update `deptrac.yaml`
12. Run QA: `phpstan` → `phpcs` → `deptrac` → `vue build`

---

## QA Gates

```bash
docker compose exec back composer phpstan
docker compose exec back composer phpcs
docker compose exec back vendor/bin/deptrac analyse
docker compose exec app npm run build
make restart-worker
```

---

## File inventory

### New files (27)
```
back/src/Notification/Shared/Event/RssFetchStartedEvent.php
back/src/Notification/Shared/Event/RssFetchSucceededEvent.php
back/src/Notification/Shared/Event/RssFetchFailedEvent.php
back/src/Notification/Shared/Event/JikanFetchStartedEvent.php
back/src/Notification/Shared/Event/JikanFetchSucceededEvent.php
back/src/Notification/Shared/Event/JikanFetchFailedEvent.php
back/src/Notification/Shared/Event/DiscordNotificationSentEvent.php
back/src/Notification/Shared/Event/DiscordNotificationSkippedEvent.php
back/src/Notification/Shared/Event/SchedulerFiredEvent.php
back/src/Shared/Event/UserActionEvent.php
back/src/Notification/Domain/Service/RssFeedParser.php
back/src/Notification/Domain/Service/RssFeedParserException.php
back/src/Notification/Domain/Service/JikanNewsFetcher.php
back/src/Notification/Infrastructure/Listener/ActivityLogRssFetchStartedListener.php
back/src/Notification/Infrastructure/Listener/ActivityLogRssFetchSucceededListener.php
back/src/Notification/Infrastructure/Listener/ActivityLogRssFetchFailedListener.php
back/src/Notification/Infrastructure/Listener/ActivityLogJikanFetchStartedListener.php
back/src/Notification/Infrastructure/Listener/ActivityLogJikanFetchSucceededListener.php
back/src/Notification/Infrastructure/Listener/ActivityLogJikanFetchFailedListener.php
back/src/Notification/Infrastructure/Listener/ActivityLogDiscordSentListener.php
back/src/Notification/Infrastructure/Listener/ActivityLogSchedulerFiredListener.php
back/src/Notification/Infrastructure/Listener/DiscordRssFetchSucceededListener.php
back/src/Notification/Infrastructure/Listener/DiscordJikanFetchSucceededListener.php
back/src/Notification/Infrastructure/Listener/DiscordNotificationSentListener.php
back/src/Notification/Infrastructure/Http/ActivityLogKernelSubscriber.php
back/src/Notification/Infrastructure/Http/ActivityLogUserActionListener.php
```

### Modified files (9)
```
back/src/Notification/Application/Fetch/FetchRssFeedHandler.php
back/src/Notification/Application/Fetch/FetchJikanNewsHandler.php
back/src/Notification/Application/Discord/SendDiscordNotificationHandler.php
back/src/Notification/Application/Schedule/DispatchFollowingCrawlTask.php
back/src/Notification/Domain/ActivityLogRepositoryInterface.php
back/src/Notification/Infrastructure/Doctrine/DoctrineActivityLogRepository.php
back/src/Notification/Infrastructure/Messenger/WorkerFailureSubscriber.php
back/config/services.yaml
back/deptrac.yaml
```
