# Plan: Monitoring & Observability Overhaul

## Context & Goal
Replace the existing rudimentary email notification and minimal activity log with a full
observability stack: Discord webhook alerts (new articles + error spikes + worker down),
structured Monolog logging for Zenstruck Messenger Monitor, an enhanced `ActivityLog`
entity tracking all system events with typed metadata, and a rich in-app Journal page
showing the full event history filterable by type/status/manga.

After this plan is executed:
- New articles → Discord embed per manga (email removed)
- Worker crash or >5 handler failures in 10 min → Discord `@here` warning
- Every RSS fetch, Jikan fetch, scheduler fire, Discord send, and 500 error is logged
  as a structured `ActivityLog` row with a JSON `metadata` field
- The `/journal` frontend page shows this history paginated with filters

---

## Scope

**In scope:**
- `EventTypeEnum` PHP enum replacing the raw `sourceType` string
- `ActivityLog` entity: make `collectionEntry` nullable, add `eventType`, `metadata`
- `DiscordNotifier` service (HttpClient, embeds, cooldown via ActivityLog count)
- Replace `SendFollowingNotificationHandler` (email) with `SendDiscordNotificationHandler`
- Fetch handlers dispatch Discord notification when `newCount > 0`
- `WorkerFailureSubscriber` listens to `WorkerMessageFailedEvent`, counts recent errors,
  fires Discord alert if threshold exceeded
- `ActivityLogRepositoryInterface` + Doctrine impl: add `findPaginated(filters)` and
  `countRecentErrors(windowMinutes)`
- `GetActivityLogsQuery/Handler`: pagination + filters (eventType, status, collectionEntryId)
- `GET /api/articles/activity-logs` updated: page, limit, eventType, status, collectionEntryId
- Monolog channel `monitoring` with proper context
- Frontend: `JournalPage.vue` at `/journal`, nav link, i18n keys

**Out of scope:**
- External uptime monitoring (no pinging Ziggy from outside)
- PHPUnit tests (no test suite exists yet)
- Email notifications (fully removed)
- Retention/cleanup cron for old logs (future)

---

## Architecture Overview

`WorkerMessageFailedEvent` → `WorkerFailureSubscriber` → count recent errors in DB →
if threshold → `DiscordNotifier::sendAlert()`.

Fetch handlers → save `ActivityLog` (eventType=rss_fetch|jikan_fetch) → if newCount > 0
→ dispatch `SendDiscordNotificationMessage(async)` → `SendDiscordNotificationHandler`
→ `DiscordNotifier::sendNewArticles()` → save ActivityLog(eventType=discord_sent).

`GET /api/articles/activity-logs?page=1&limit=50&eventType=rss_fetch&status=error`
→ `ArticleController::activityLogs()` → `GetActivityLogsQuery` → handler
→ `ActivityLogRepository::findPaginated()` → JSON.

Vue: `JournalPage` (page, calls `getActivityLogs()`) → passes props to `ActivityLogTable`
(organism) → `ActivityLogRow` (molecule) → `ActivityLogDetailModal` (molecule).

---

## Backend Steps

---

### Step 1 — Domain: EventTypeEnum
**File:** `back/src/Notification/Domain/EventTypeEnum.php` *(create)*
**Why:** Replaces the raw `sourceType: string` with a typed enum, making log entries
machine-readable and safe to filter on.

```php
<?php

declare(strict_types=1);

namespace App\Notification\Domain;

enum EventTypeEnum: string
{
    case RssFetch      = 'rss_fetch';
    case JikanFetch    = 'jikan_fetch';
    case DiscordSent   = 'discord_sent';
    case SchedulerFire = 'scheduler_fire';
    case HttpError     = 'http_error';
    case WorkerFailure = 'worker_failure';
}
```

---

### Step 2 — Domain: Update ActivityLog entity
**File:** `back/src/Notification/Domain/ActivityLog.php` *(modify)*
**Why:** Adds typed `eventType`, JSON `metadata`, makes `collectionEntry` nullable so
system-level events (scheduler, worker, 500 errors) can be logged without a manga.

Replace the entire entity with this:

```php
<?php

declare(strict_types=1);

namespace App\Notification\Domain;

use App\Collection\Domain\CollectionEntry;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_logs')]
class ActivityLog
{
    #[ORM\Column]
    public \DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $finishedAt = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public readonly string $id,

        #[ORM\Column(enumType: EventTypeEnum::class)]
        public readonly EventTypeEnum $eventType,

        #[ORM\Column(length: 100)]
        public readonly string $sourceName,

        #[ORM\ManyToOne(targetEntity: CollectionEntry::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        public readonly ?CollectionEntry $collectionEntry = null,

        /** 'running' | 'success' | 'error' */
        #[ORM\Column(length: 20)]
        public string $status = 'running',

        #[ORM\Column(type: 'text', nullable: true)]
        public ?string $errorMessage = null,

        #[ORM\Column(nullable: true)]
        public ?int $newArticlesCount = null,

        /** @var array<string, mixed> */
        #[ORM\Column(type: 'json', nullable: true)]
        public ?array $metadata = null,
    ) {
        $this->startedAt = new \DateTimeImmutable();
    }

    public function markSuccess(int $newArticlesCount = 0, array $metadata = []): void
    {
        $this->status           = 'success';
        $this->newArticlesCount = $newArticlesCount;
        $this->finishedAt       = new \DateTimeImmutable();
        if ($metadata !== []) {
            $this->metadata = array_merge($this->metadata ?? [], $metadata);
        }
    }

    public function markError(string $message, array $metadata = []): void
    {
        $this->status       = 'error';
        $this->errorMessage = mb_substr($message, 0, 2000);
        $this->finishedAt   = new \DateTimeImmutable();
        if ($metadata !== []) {
            $this->metadata = array_merge($this->metadata ?? [], $metadata);
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'eventType'        => $this->eventType->value,
            'sourceName'       => $this->sourceName,
            'collectionEntryId'=> $this->collectionEntry?->id,
            'mangaTitle'       => $this->collectionEntry?->manga->title,
            'status'           => $this->status,
            'errorMessage'     => $this->errorMessage,
            'newArticlesCount' => $this->newArticlesCount,
            'metadata'         => $this->metadata,
            'startedAt'        => $this->startedAt->format(\DateTimeInterface::ATOM),
            'finishedAt'       => $this->finishedAt?->format(\DateTimeInterface::ATOM),
            'durationMs'       => $this->finishedAt !== null
                ? (int) (($this->finishedAt->format('U.u') - $this->startedAt->format('U.u')) * 1000)
                : null,
        ];
    }
}
```

> The old `sourceType` string is removed; callers must be updated to pass `eventType: EventTypeEnum::RssFetch` etc.

---

### Step 3 — Domain: ActivityLogRepositoryInterface — add query methods
**File:** `back/src/Notification/Domain/ActivityLogRepositoryInterface.php` *(modify)*
**Why:** Exposes paginated filtered queries and the error-count method needed for alerting.

```php
<?php

declare(strict_types=1);

namespace App\Notification\Domain;

interface ActivityLogRepositoryInterface
{
    public function save(ActivityLog $log): void;

    /**
     * @param array{eventType?: string, status?: string, collectionEntryId?: string} $filters
     * @return array{items: ActivityLog[], total: int}
     */
    public function findPaginated(int $page, int $limit, array $filters = []): array;

    public function countRecentErrors(int $windowMinutes = 10): int;
}
```

> Remove the old `findRecent(int $limit)` signature — it is replaced by `findPaginated`.

---

### Step 4 — Infrastructure: Discord Notifier service
**File:** `back/src/Notification/Infrastructure/Discord/DiscordNotifier.php` *(create)*
**Why:** Single service responsible for all Discord webhook calls, formatting embeds,
and swallowing send errors gracefully so a Discord outage never breaks the worker.

```php
<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Discord;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class DiscordNotifier
{
    // Discord colour codes (decimal)
    private const COLOR_GREEN  = 3_066_993;
    private const COLOR_ORANGE = 15_105_570;
    private const COLOR_RED    = 15_158_332;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private ?string $webhookUrl,
    ) {}

    public function isConfigured(): bool
    {
        return $this->webhookUrl !== null && $this->webhookUrl !== '';
    }

    /**
     * Rich embed: new articles found for a manga.
     *
     * @param array<int, array<string, mixed>> $articles
     */
    public function sendNewArticles(
        string $mangaTitle,
        ?string $mangaCoverUrl,
        int $count,
        array $articles,
    ): void {
        $fields = [];
        foreach (array_slice($articles, 0, 5) as $a) {
            $fields[] = [
                'name'   => mb_substr((string) $a['title'], 0, 256),
                'value'  => sprintf('[%s](%s)', $a['sourceName'], $a['url']),
                'inline' => false,
            ];
        }

        $embed = [
            'title'       => sprintf('📰 %d nouveau%s article%s — %s', $count, $count > 1 ? 'x' : '', $count > 1 ? 's' : '', $mangaTitle),
            'color'       => self::COLOR_GREEN,
            'fields'      => $fields,
            'footer'      => ['text' => 'Ziggytheque'],
            'timestamp'   => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        if ($mangaCoverUrl !== null) {
            $embed['thumbnail'] = ['url' => $mangaCoverUrl];
        }

        $this->send(['embeds' => [$embed]]);
    }

    /**
     * Alert embed: worker failure or error spike.
     */
    public function sendAlert(string $title, string $description, bool $critical = false): void
    {
        $this->send([
            'content' => $critical ? '@here' : null,
            'embeds'  => [[
                'title'       => ($critical ? '🚨 ' : '⚠️ ') . $title,
                'description' => mb_substr($description, 0, 4096),
                'color'       => $critical ? self::COLOR_RED : self::COLOR_ORANGE,
                'footer'      => ['text' => 'Ziggytheque Monitor'],
                'timestamp'   => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ]],
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function send(array $payload): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        try {
            $this->httpClient->request('POST', $this->webhookUrl, [
                'json'    => $payload,
                'timeout' => 5,
            ])->getStatusCode(); // consume response
        } catch (\Throwable $e) {
            // Never let Discord failures bubble up into the worker
            $this->logger->warning('Discord webhook failed', ['error' => $e->getMessage()]);
        }
    }
}
```

---

### Step 5 — Config: wire DiscordNotifier in services.yaml
**File:** `back/config/services.yaml` *(modify)*
**Why:** Injects the `DISCORD_WEBHOOK_URL` env var into the notifier.

Add inside the `services:` block (below the existing bindings):

```yaml
    App\Notification\Infrastructure\Discord\DiscordNotifier:
        arguments:
            $webhookUrl: '%env(default::DISCORD_WEBHOOK_URL)%'
```

Also add to the `parameters:` block at the top:

```yaml
    discord_webhook_url: '%env(default::DISCORD_WEBHOOK_URL)%'
```

And add to `back/.env` (no value — user fills it in):
```dotenv
DISCORD_WEBHOOK_URL=
```

---

### Step 6 — Application: SendDiscordNotificationMessage + Handler
**File:** `back/src/Notification/Application/Discord/SendDiscordNotificationMessage.php` *(create)*

```php
<?php

declare(strict_types=1);

namespace App\Notification\Application\Discord;

final readonly class SendDiscordNotificationMessage
{
    public function __construct(
        public string $collectionEntryId,
        /** @var string[] */
        public array $articleIds,
    ) {}
}
```

**File:** `back/src/Notification/Application/Discord/SendDiscordNotificationHandler.php` *(create)*
**Why:** Replaces `SendFollowingNotificationHandler` (email). Sends a Discord embed and
logs the send as an `ActivityLog(eventType=discord_sent)`.

```php
<?php

declare(strict_types=1);

namespace App\Notification\Application\Discord;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Domain\ArticleRepositoryInterface;
use App\Notification\Domain\EventTypeEnum;
use App\Notification\Infrastructure\Discord\DiscordNotifier;
use App\Shared\Domain\Exception\NotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class SendDiscordNotificationHandler
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private ArticleRepositoryInterface $articleRepository,
        private ActivityLogRepositoryInterface $activityLogRepository,
        private DiscordNotifier $discord,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(SendDiscordNotificationMessage $message): void
    {
        $entry = $this->collectionRepository->findById($message->collectionEntryId);
        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $message->collectionEntryId);
        }

        // Cooldown: skip if already notified in the last 12 hours
        if (
            $entry->lastNotifiedAt !== null
            && $entry->lastNotifiedAt > new \DateTimeImmutable('-12 hours')
        ) {
            $this->logger->info('Discord notification skipped (cooldown)', [
                'manga' => $entry->manga->title,
            ]);
            return;
        }

        $result   = $this->articleRepository->findPaginated(1, 10, $entry->id);
        $articles = array_map(static fn ($a) => $a->toArray(), $result['items']);

        if (empty($articles)) {
            return;
        }

        $log = new ActivityLog(
            id: Uuid::v4()->toRfc4122(),
            eventType: EventTypeEnum::DiscordSent,
            sourceName: 'discord',
            collectionEntry: $entry,
        );
        $this->activityLogRepository->save($log);

        $this->discord->sendNewArticles(
            mangaTitle: $entry->manga->title,
            mangaCoverUrl: $entry->manga->coverUrl,
            count: count($articles),
            articles: $articles,
        );

        $entry->lastNotifiedAt = new \DateTimeImmutable();
        $this->collectionRepository->save($entry);

        $log->markSuccess(count($articles), ['articleCount' => count($articles)]);
        $this->activityLogRepository->save($log);

        $this->logger->info('Discord notification sent', [
            'manga'   => $entry->manga->title,
            'articles'=> count($articles),
        ]);
    }
}
```

---

### Step 7 — Config: route SendDiscordNotificationMessage to async transport
**File:** `back/config/packages/messenger.yaml` *(modify)*
**Why:** The Discord handler must run in the worker, not synchronously in the scheduler.

In the `routing:` section, replace:
```yaml
            App\Notification\Application\Email\SendFollowingNotificationMessage: async
```
with:
```yaml
            App\Notification\Application\Discord\SendDiscordNotificationMessage: async
```

---

### Step 8 — Application: Update FetchRssFeedHandler — use new ActivityLog fields + dispatch Discord
**File:** `back/src/Notification/Application/Fetch/FetchRssFeedHandler.php` *(modify)*
**Why:** Uses `EventTypeEnum` instead of raw string, dispatches `SendDiscordNotificationMessage`
when articles are found, enriches `markSuccess` / `markError` with metadata.

Key changes:

```php
// Constructor: add MessageBusInterface
public function __construct(
    private HttpClientInterface $httpClient,
    private CollectionRepositoryInterface $collectionRepository,
    private ArticleRepositoryInterface $articleRepository,
    private ActivityLogRepositoryInterface $activityLogRepository,
    private MessageBusInterface $messageBus,   // ← add
    private LoggerInterface $logger,
) {}

// In __invoke, replace ActivityLog construction:
$log = new ActivityLog(
    id: Uuid::v4()->toRfc4122(),
    eventType: EventTypeEnum::RssFetch,        // ← was: sourceType: 'rss', sourceName: ...
    sourceName: $message->feedName,
    collectionEntry: $entry,
    metadata: ['feed_url' => $message->feedUrl, 'manga' => $message->mangaTitle],
);

// markSuccess call:
$log->markSuccess($newCount, ['items_scanned' => iterator_count($channel->item ?? [])]);

// markError call:
$log->markError($e->getMessage(), ['exception_class' => $e::class]);

// After markSuccess, if newCount > 0:
if ($newCount > 0) {
    $this->messageBus->dispatch(new SendDiscordNotificationMessage(
        collectionEntryId: $entry->id,
        articleIds: [],
    ));
}
```

> Add `use App\Notification\Application\Discord\SendDiscordNotificationMessage;` and
> `use App\Notification\Domain\EventTypeEnum;` imports.
> Remove old `use` for `sourceType` if any.

---

### Step 9 — Application: Update FetchJikanNewsHandler — same pattern
**File:** `back/src/Notification/Application/Fetch/FetchJikanNewsHandler.php` *(modify)*
**Why:** Same as Step 8: typed enum, Discord dispatch, richer metadata.

```php
// Constructor: add MessageBusInterface
// ActivityLog construction:
$log = new ActivityLog(
    id: Uuid::v4()->toRfc4122(),
    eventType: EventTypeEnum::JikanFetch,
    sourceName: 'jikan-news',
    collectionEntry: $entry,
    metadata: ['mal_id' => $message->malId, 'manga' => $message->mangaTitle],
);

// markSuccess:
$log->markSuccess($newCount, ['items_received' => count($items)]);

// markError:
$log->markError($e->getMessage(), ['exception_class' => $e::class]);

// After markSuccess, if newCount > 0:
if ($newCount > 0) {
    $this->messageBus->dispatch(new SendDiscordNotificationMessage(
        collectionEntryId: $entry->id,
        articleIds: [],
    ));
}
```

---

### Step 10 — Application: Update DispatchFollowingCrawlTask — log scheduler fire
**File:** `back/src/Notification/Application/Schedule/DispatchFollowingCrawlTask.php` *(modify)*
**Why:** Logs each scheduler execution as `EventTypeEnum::SchedulerFire` so the journal
shows when crawls were triggered.

```php
// Constructor: add ActivityLogRepositoryInterface
public function __construct(
    private CollectionRepositoryInterface $collectionRepository,
    private MessageBusInterface $messageBus,
    private ActivityLogRepositoryInterface $activityLogRepository,  // ← add
    private LoggerInterface $logger,
    private array $rssFeeds,
) {}

// At start of __invoke:
$log = new ActivityLog(
    id: Uuid::v4()->toRfc4122(),
    eventType: EventTypeEnum::SchedulerFire,
    sourceName: 'scheduler',
);
$this->activityLogRepository->save($log);

// At end:
$log->markSuccess(0, ['followed' => count($followed), 'jobs_dispatched' => $totalJobs]);
$this->activityLogRepository->save($log);
```

---

### Step 11 — Infrastructure: WorkerFailureSubscriber
**File:** `back/src/Notification/Infrastructure/Messenger/WorkerFailureSubscriber.php` *(create)*
**Why:** Listens to `WorkerMessageFailedEvent`, logs the failure as an `ActivityLog`, and
sends a Discord alert if more than 5 errors occurred in the last 10 minutes.

```php
<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Messenger;

use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Domain\EventTypeEnum;
use App\Notification\Infrastructure\Discord\DiscordNotifier;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Uuid;

#[AsEventListener]
final readonly class WorkerFailureSubscriber
{
    private const ERROR_THRESHOLD  = 5;
    private const WINDOW_MINUTES   = 10;

    public function __construct(
        private ActivityLogRepositoryInterface $activityLogRepository,
        private DiscordNotifier $discord,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $throwable   = $event->getThrowable();
        $messageName = get_class($event->getEnvelope()->getMessage());

        $log = new ActivityLog(
            id: Uuid::v4()->toRfc4122(),
            eventType: EventTypeEnum::WorkerFailure,
            sourceName: 'worker',
            metadata: [
                'message_class' => $messageName,
                'exception'     => $throwable::class,
                'will_retry'    => !$event->willRetry(),
            ],
        );
        $log->markError($throwable->getMessage());
        $this->activityLogRepository->save($log);

        $this->logger->error('Worker message failed', [
            'message'   => $messageName,
            'exception' => $throwable::class,
            'error'     => $throwable->getMessage(),
            'will_retry'=> $event->willRetry(),
        ]);

        // Only alert on final failure (no more retries)
        if ($event->willRetry()) {
            return;
        }

        $recentErrors = $this->activityLogRepository->countRecentErrors(self::WINDOW_MINUTES);

        if ($recentErrors >= self::ERROR_THRESHOLD) {
            $this->discord->sendAlert(
                title: sprintf('%d erreurs worker en %d min', $recentErrors, self::WINDOW_MINUTES),
                description: sprintf(
                    "**Message:** `%s`\n**Erreur:** %s",
                    $messageName,
                    mb_substr($throwable->getMessage(), 0, 500),
                ),
                critical: true,
            );
        }
    }
}
```

---

### Step 12 — Infrastructure: DoctrineActivityLogRepository — implement new methods
**File:** `back/src/Notification/Infrastructure/Doctrine/DoctrineActivityLogRepository.php` *(modify)*
**Why:** Implements the two new interface methods: paginated filtered query and error count.

Replace `findRecent()` with `findPaginated()` and add `countRecentErrors()`:

```php
public function findPaginated(int $page, int $limit, array $filters = []): array
{
    $qb = $this->em->createQueryBuilder()
        ->select('l')
        ->from(ActivityLog::class, 'l')
        ->leftJoin('l.collectionEntry', 'ce')
        ->orderBy('l.startedAt', 'DESC');

    if (isset($filters['eventType'])) {
        $qb->andWhere('l.eventType = :et')
           ->setParameter('et', $filters['eventType']);
    }

    if (isset($filters['status'])) {
        $qb->andWhere('l.status = :status')
           ->setParameter('status', $filters['status']);
    }

    if (isset($filters['collectionEntryId'])) {
        $qb->andWhere('ce.id = :ceId')
           ->setParameter('ceId', $filters['collectionEntryId']);
    }

    $total = (clone $qb)->select('COUNT(l.id)')->resetDQLPart('orderBy')
        ->getQuery()->getSingleScalarResult();

    $items = $qb
        ->setFirstResult(($page - 1) * $limit)
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();

    return ['items' => $items, 'total' => (int) $total];
}

public function countRecentErrors(int $windowMinutes = 10): int
{
    $since = new \DateTimeImmutable("-{$windowMinutes} minutes");

    return (int) $this->em->createQueryBuilder()
        ->select('COUNT(l.id)')
        ->from(ActivityLog::class, 'l')
        ->where('l.status = :status')
        ->andWhere('l.startedAt >= :since')
        ->setParameter('status', 'error')
        ->setParameter('since', $since)
        ->getQuery()
        ->getSingleScalarResult();
}
```

---

### Step 13 — Application: Update GetActivityLogsQuery + Handler — pagination + filters
**File:** `back/src/Notification/Application/GetActivityLogs/GetActivityLogsQuery.php` *(modify)*

```php
<?php

declare(strict_types=1);

namespace App\Notification\Application\GetActivityLogs;

final readonly class GetActivityLogsQuery
{
    public function __construct(
        public int $page = 1,
        public int $limit = 50,
        public ?string $eventType = null,
        public ?string $status = null,
        public ?string $collectionEntryId = null,
    ) {}
}
```

**File:** `back/src/Notification/Application/GetActivityLogs/GetActivityLogsHandler.php` *(modify)*

```php
public function __invoke(GetActivityLogsQuery $query): array
{
    $filters = array_filter([
        'eventType'         => $query->eventType,
        'status'            => $query->status,
        'collectionEntryId' => $query->collectionEntryId,
    ]);

    $result = $this->repository->findPaginated($query->page, $query->limit, $filters);

    return [
        'items'      => array_map(static fn ($l) => $l->toArray(), $result['items']),
        'total'      => $result['total'],
        'page'       => $query->page,
        'limit'      => $query->limit,
        'totalPages' => (int) ceil($result['total'] / $query->limit),
    ];
}
```

---

### Step 14 — Infrastructure: Update ArticleController — activityLogs endpoint
**File:** `back/src/Notification/Infrastructure/Http/ArticleController.php` *(modify)*
**Why:** Exposes new filter params to the frontend.

```php
#[Route('/activity-logs', methods: ['GET'])]
public function activityLogs(Request $request): JsonResponse
{
    $page              = max(1, (int) $request->query->get('page', 1));
    $limit             = min(100, max(1, (int) $request->query->get('limit', 50)));
    $eventType         = $request->query->get('eventType') ?: null;
    $status            = $request->query->get('status') ?: null;
    $collectionEntryId = $request->query->get('collectionEntryId') ?: null;

    return new JsonResponse(
        $this->queryBus->ask(new GetActivityLogsQuery($page, $limit, $eventType, $status, $collectionEntryId)),
    );
}
```

---

### Step 15 — Config: Update services.yaml for DispatchFollowingCrawlTask
**File:** `back/config/services.yaml` *(modify)*
**Why:** Inject `ActivityLogRepositoryInterface` into the scheduler task.

```yaml
    App\Notification\Application\Schedule\DispatchFollowingCrawlTask:
        arguments:
            $rssFeeds: '%following.rss_feeds%'
            # ActivityLogRepositoryInterface autowired via alias
```

> No explicit argument needed — autowiring resolves via the interface alias already
> defined. Just ensure `ActivityLogRepositoryInterface` alias points to the Doctrine impl.

---

### Step 16 — Config: Add DISCORD_WEBHOOK_URL env to docker-compose
**File:** `docker-compose.yml` *(modify)*
**Why:** Both `back` and `worker` containers need the Discord env var.

Add to both `back` and `worker` environment blocks:
```yaml
      DISCORD_WEBHOOK_URL: ${DISCORD_WEBHOOK_URL:-}
```

---

## Database Migration

**Why:** Three schema changes: (1) `collection_entry_id` becomes nullable in
`activity_logs` (system events have no manga), (2) `source_type` column renamed/retyped
to `event_type` as a proper enum string, (3) `metadata` JSON column added.

```bash
# Generate diff
docker compose exec back php bin/console doctrine:migrations:diff

# Review back/migrations/Version202604XXXXXX.php — verify:
# - ALTER TABLE activity_logs ALTER COLUMN collection_entry_id DROP NOT NULL
# - ALTER TABLE activity_logs ADD COLUMN event_type VARCHAR(30) NOT NULL DEFAULT 'rss_fetch'
# - ALTER TABLE activity_logs ADD COLUMN metadata JSON DEFAULT NULL
# - (source_type may be renamed or left for compat — check diff output)

# Apply
docker compose exec back php bin/console doctrine:migrations:migrate --no-interaction
```

> After reviewing the diff, also add a data migration for existing rows:
> `UPDATE activity_logs SET event_type = source_type WHERE event_type IS NULL`
> (if Doctrine names the new column differently from `source_type`).

---

## Frontend Steps

---

### Step 17 — Types: Update ActivityLog + add ActivityLogPage
**File:** `front/src/types/index.ts` *(modify)*

```typescript
export type EventType =
  | 'rss_fetch'
  | 'jikan_fetch'
  | 'discord_sent'
  | 'scheduler_fire'
  | 'http_error'
  | 'worker_failure'

export type LogStatus = 'running' | 'success' | 'error'

export interface ActivityLog {
  id: string
  eventType: EventType
  sourceName: string
  collectionEntryId: string | null
  mangaTitle: string | null
  status: LogStatus
  errorMessage: string | null
  newArticlesCount: number | null
  metadata: Record<string, unknown> | null
  durationMs: number | null
  startedAt: string
  finishedAt: string | null
}

export interface ActivityLogPage {
  items: ActivityLog[]
  total: number
  page: number
  limit: number
  totalPages: number
}
```

---

### Step 18 — API: Update getActivityLogs
**File:** `front/src/api/notification.ts` *(modify)*

```typescript
export interface ActivityLogParams {
  page?: number
  limit?: number
  eventType?: string
  status?: string
  collectionEntryId?: string
}

export async function getActivityLogs(params: ActivityLogParams = {}): Promise<ActivityLogPage> {
  const res = await client.get('/articles/activity-logs', { params })
  return res.data
}
```

---

### Step 19 — Component: ActivityLogRow molecule
**File:** `front/src/components/molecules/ActivityLogRow.vue` *(create)*
**Why:** Renders a single log entry row with colored status badge, event type label,
manga name, duration, and expandable error/metadata section.

```vue
<script setup lang="ts">
import type { ActivityLog, EventType } from '@/types'
import { ref } from 'vue'

const props = defineProps<{ log: ActivityLog }>()
const expanded = ref(false)

const eventTypeLabel: Record<EventType, string> = {
  rss_fetch:      'RSS',
  jikan_fetch:    'Jikan',
  discord_sent:   'Discord',
  scheduler_fire: 'Scheduler',
  http_error:     'HTTP Error',
  worker_failure: 'Worker',
}

const statusClass: Record<string, string> = {
  running: 'badge-warning',
  success: 'badge-success',
  error:   'badge-error',
}
</script>

<template>
  <tr
    class="hover:bg-base-200/50 cursor-pointer transition-colors"
    :class="{ 'bg-error/5': log.status === 'error' }"
    @click="expanded = !expanded"
  >
    <td class="text-[11px] text-base-content/50 whitespace-nowrap">
      {{ new Date(log.startedAt).toLocaleString('fr-FR') }}
    </td>
    <td>
      <span class="badge badge-xs badge-outline font-mono">
        {{ eventTypeLabel[log.eventType] ?? log.eventType }}
      </span>
    </td>
    <td class="text-xs truncate max-w-40">{{ log.mangaTitle ?? '—' }}</td>
    <td class="text-xs text-base-content/60 truncate max-w-32">{{ log.sourceName }}</td>
    <td>
      <span class="badge badge-xs" :class="statusClass[log.status]">{{ log.status }}</span>
    </td>
    <td class="text-xs text-right tabular-nums">
      <span v-if="log.newArticlesCount !== null" class="text-success font-semibold">
        +{{ log.newArticlesCount }}
      </span>
      <span v-else>—</span>
    </td>
    <td class="text-xs text-right tabular-nums text-base-content/40">
      {{ log.durationMs !== null ? `${log.durationMs}ms` : '—' }}
    </td>
  </tr>
  <!-- Expanded detail row -->
  <tr v-if="expanded">
    <td colspan="7" class="bg-base-200 px-4 py-3 text-xs font-mono">
      <div v-if="log.errorMessage" class="text-error mb-2">{{ log.errorMessage }}</div>
      <pre v-if="log.metadata" class="text-base-content/60 whitespace-pre-wrap text-[10px]">{{ JSON.stringify(log.metadata, null, 2) }}</pre>
    </td>
  </tr>
</template>
```

---

### Step 20 — Page: JournalPage
**File:** `front/src/pages/JournalPage.vue` *(create)*
**Why:** The main activity journal — full-width table with filters for eventType, status,
and manga, paginated, auto-refreshes every 30 s via `refetchInterval`.

```vue
<script setup lang="ts">
import { ref, computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import { getActivityLogs } from '@/api/notification'
import { getCollection } from '@/api/collection'
import ActivityLogRow from '@/components/molecules/ActivityLogRow.vue'
import type { EventType, LogStatus } from '@/types'

const { t } = useI18n()

const page      = ref(1)
const limit     = 50
const eventType = ref<EventType | ''>('')
const status    = ref<LogStatus | ''>('')
const ceId      = ref<string>('')

const { data: collection } = useQuery({ queryKey: ['collection'], queryFn: getCollection })

const { data, isPending } = useQuery({
  queryKey: computed(() => ['journal', page.value, eventType.value, status.value, ceId.value]),
  queryFn: () => getActivityLogs({
    page: page.value,
    limit,
    eventType: eventType.value || undefined,
    status:    status.value    || undefined,
    collectionEntryId: ceId.value || undefined,
  }),
  refetchInterval: 30_000,
})

const EVENT_TYPES: { value: EventType | ''; label: string }[] = [
  { value: '',               label: t('journal.allTypes') },
  { value: 'rss_fetch',     label: 'RSS' },
  { value: 'jikan_fetch',   label: 'Jikan' },
  { value: 'discord_sent',  label: 'Discord' },
  { value: 'scheduler_fire',label: 'Scheduler' },
  { value: 'worker_failure',label: 'Worker' },
]

const STATUSES: { value: LogStatus | ''; label: string }[] = [
  { value: '',        label: t('journal.allStatuses') },
  { value: 'running', label: t('journal.running') },
  { value: 'success', label: t('journal.success') },
  { value: 'error',   label: t('journal.error') },
]
</script>

<template>
  <div class="p-4 sm:p-6 space-y-4">
    <h1 class="text-2xl font-bold">{{ t('journal.title') }}</h1>

    <!-- Filters -->
    <div class="flex gap-3 flex-wrap items-center">
      <select v-model="eventType" class="select select-sm select-bordered" @change="page = 1">
        <option v-for="o in EVENT_TYPES" :key="o.value" :value="o.value">{{ o.label }}</option>
      </select>
      <select v-model="status" class="select select-sm select-bordered" @change="page = 1">
        <option v-for="o in STATUSES" :key="o.value" :value="o.value">{{ o.label }}</option>
      </select>
      <select v-model="ceId" class="select select-sm select-bordered" @change="page = 1">
        <option value="">{{ t('journal.allMangas') }}</option>
        <option v-for="e in collection" :key="e.id" :value="e.id">{{ e.manga.title }}</option>
      </select>
      <span class="text-xs text-base-content/40 ml-auto">
        {{ data?.total ?? 0 }} {{ t('journal.entries') }}
      </span>
    </div>

    <!-- Loading skeleton -->
    <div v-if="isPending" class="space-y-1">
      <div v-for="i in 10" :key="i" class="h-10 rounded bg-base-200 animate-pulse" />
    </div>

    <!-- Table -->
    <div v-else class="overflow-x-auto rounded-xl border border-base-300">
      <table class="table table-xs w-full">
        <thead>
          <tr class="text-base-content/50">
            <th>{{ t('journal.date') }}</th>
            <th>{{ t('journal.type') }}</th>
            <th>{{ t('journal.manga') }}</th>
            <th>{{ t('journal.source') }}</th>
            <th>{{ t('journal.status') }}</th>
            <th class="text-right">{{ t('journal.articles') }}</th>
            <th class="text-right">{{ t('journal.duration') }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="log in data?.items ?? []" :key="log.id">
            <ActivityLogRow :log="log" />
          </template>
        </tbody>
      </table>
      <div v-if="!data?.items?.length" class="py-12 text-center text-base-content/40 text-sm">
        {{ t('journal.empty') }}
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="(data?.totalPages ?? 0) > 1" class="flex justify-center gap-2">
      <button class="btn btn-sm btn-ghost" :disabled="page === 1" @click="page--">‹</button>
      <button
        v-for="p in data!.totalPages"
        :key="p"
        class="btn btn-sm"
        :class="p === page ? 'btn-primary' : 'btn-ghost'"
        @click="page = p"
      >{{ p }}</button>
      <button class="btn btn-sm btn-ghost" :disabled="page === data!.totalPages" @click="page++">›</button>
    </div>
  </div>
</template>
```

---

### Step 21 — Router: Add /journal route
**File:** `front/src/router/index.ts` *(modify)*

Add inside the authenticated children array:
```typescript
{
  path: 'journal',
  name: 'journal',
  component: () => import('@/pages/JournalPage.vue'),
  meta: { title: 'Journal' },
},
```

---

### Step 22 — MainLayout: Add Journal nav link
**File:** `front/src/components/organisms/MainLayout.vue` *(modify)*
**Why:** Users need to reach the journal from the sidebar.

Find the nav links block and add alongside `/notifications`:
```html
<RouterLink to="/journal" class="...">
  <!-- icon: ClipboardList or similar -->
  {{ t('nav.journal') }}
</RouterLink>
```

Use the same CSS classes as the existing nav links.

---

## i18n Keys

**`front/src/i18n/fr.json`** — add inside root object:
```json
"journal": {
  "title": "Journal d'activité",
  "allTypes": "Tous les types",
  "allStatuses": "Tous les statuts",
  "allMangas": "Tous les mangas",
  "running": "En cours",
  "success": "Succès",
  "error": "Erreur",
  "date": "Date",
  "type": "Type",
  "manga": "Manga",
  "source": "Source",
  "status": "Statut",
  "articles": "Articles",
  "duration": "Durée",
  "entries": "entrées",
  "empty": "Aucun événement enregistré"
},
"nav": {
  "journal": "Journal"
}
```

**`front/src/i18n/en.json`** — add:
```json
"journal": {
  "title": "Activity Journal",
  "allTypes": "All types",
  "allStatuses": "All statuses",
  "allMangas": "All mangas",
  "running": "Running",
  "success": "Success",
  "error": "Error",
  "date": "Date",
  "type": "Type",
  "manga": "Manga",
  "source": "Source",
  "status": "Status",
  "articles": "Articles",
  "duration": "Duration",
  "entries": "entries",
  "empty": "No events recorded"
},
"nav": {
  "journal": "Journal"
}
```

---

## QA Gates

### 1. PHP Static Analysis
```bash
docker compose exec back ./vendor/bin/phpstan analyse --memory-limit=512M
```

### 2. PHP Code Style
```bash
docker compose exec back ./vendor/bin/php-cs-fixer fix --dry-run --diff
docker compose exec back ./vendor/bin/php-cs-fixer fix
```

### 3. Migration status
```bash
docker compose exec back php bin/console doctrine:migrations:status
```

### 4. Container restart
```bash
docker compose restart worker back
```

### 5. Frontend type check
```bash
docker compose exec app npx tsc --noEmit
```

### 6. Smoke test
1. `docker compose up -d`
2. Open `http://localhost:5173/journal`
3. Verify the table renders (even empty)
4. Set `DISCORD_WEBHOOK_URL` in `.env`, trigger a manual crawl, verify Discord message arrives
5. Navigate sidebar → Journal link visible

---

## Execution Checklist

### Backend
- [ ] Step 1 — EventTypeEnum created
- [ ] Step 2 — ActivityLog entity updated (nullable CE, eventType, metadata)
- [ ] Step 3 — ActivityLogRepositoryInterface updated (findPaginated, countRecentErrors)
- [ ] Step 4 — DiscordNotifier service created
- [ ] Step 5 — services.yaml wired for DiscordNotifier + DISCORD_WEBHOOK_URL
- [ ] Step 6 — SendDiscordNotificationMessage + Handler created
- [ ] Step 7 — messenger.yaml routing updated (Discord replaces Email)
- [ ] Step 8 — FetchRssFeedHandler updated (enum, Discord dispatch, metadata)
- [ ] Step 9 — FetchJikanNewsHandler updated (enum, Discord dispatch, metadata)
- [ ] Step 10 — DispatchFollowingCrawlTask updated (scheduler log)
- [ ] Step 11 — WorkerFailureSubscriber created
- [ ] Step 12 — DoctrineActivityLogRepository updated (findPaginated, countRecentErrors)
- [ ] Step 13 — GetActivityLogsQuery + Handler updated (pagination + filters)
- [ ] Step 14 — ArticleController::activityLogs updated (filter params)
- [ ] Step 15 — services.yaml DispatchFollowingCrawlTask binding checked
- [ ] Step 16 — docker-compose.yml DISCORD_WEBHOOK_URL added

### Database
- [ ] Migration generated (diff)
- [ ] Migration reviewed (nullable, event_type, metadata)
- [ ] Migration applied

### Frontend
- [ ] Step 17 — ActivityLog / ActivityLogPage types updated
- [ ] Step 18 — getActivityLogs API function updated
- [ ] Step 19 — ActivityLogRow molecule created
- [ ] Step 20 — JournalPage created
- [ ] Step 21 — /journal route added
- [ ] Step 22 — MainLayout nav link added
- [ ] i18n keys added to fr.json and en.json

### QA
- [ ] PHPStan passes
- [ ] CS Fixer passes
- [ ] Doctrine migrations status clean
- [ ] TypeScript noEmit passes
- [ ] docker compose restart worker back
- [ ] Smoke test done (journal renders, Discord fires)
