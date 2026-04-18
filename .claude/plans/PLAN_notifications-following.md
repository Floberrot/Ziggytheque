# Plan: Notifications & Following System

## Context & Goal

Allow the user to "follow" any manga in their collection to receive notifications when new
content appears (new volumes, anime news, articles, new editions). A twice-daily cron job
fans out one async Messenger job per source (RSS feed or API) per followed manga. Each job
fetches its source, filters results for the manga title, and saves new `Article` records.
The user receives a summary email per followed manga. The `/notifications` page is rebuilt
as a card-based feed of found articles, filterable by manga and paginated. An activity log
records every crawl job's outcome (source, status, error, article count) for debugging.

---

## Scope

**Included:**
- `notificationsEnabled` toggle per `CollectionEntry`
- `Article` entity (articles found by crawlers)
- `ActivityLog` entity (cron journal)
- Scheduler: twice-daily dispatch via `symfony/scheduler`
- Async Messenger jobs: one per RSS feed per manga + one Jikan news job per manga
- RSS feeds: 4 configurable feeds parsed with Symfony HttpClient + SimpleXML
- Jikan API: `/manga/{malId}/news` endpoint for mangas with a Jikan `externalId`
- Email notification via `symfony/mailer` + Mailpit (dev) — one HTML email per manga when new articles arrive, max once per 12h
- Frontend: rewritten `NotificationsPage.vue` (cards, filter, pagination)
- Frontend: follow toggle in `MangaDetailPage.vue`

**Out of scope:**
- Push notifications / websockets
- Custom per-user RSS feed configuration (feeds are globally configured in `services.yaml`)
- Full-text search on articles
- Multiple users (single-user app)
- The old `Notification` entity is kept as-is and not modified (its table and controller remain)

---

## Architecture Overview

```
Symfony Scheduler (twice daily)
  → DispatchFollowingCrawlTask::__invoke()
      → CollectionRepositoryInterface::findFollowed()
      → for each CollectionEntry: dispatch N × FetchRssFeedMessage + 1 × FetchJikanNewsMessage
          → async transport → worker processes each message
              → FetchRssFeedHandler: HttpClient + SimpleXML → saves Article[] + ActivityLog
              → FetchJikanNewsHandler: HttpClient → saves Article[] + ActivityLog
              → if new articles found → dispatch SendFollowingNotificationMessage → async
                  → SendFollowingNotificationHandler: checks lastNotifiedAt → Mailer

HTTP (frontend) ─────────────────────────────────────────────────────────────
GET  /api/articles?page=1&limit=12&collectionEntryId=xxx → ArticleController
PATCH /api/collection/{id}/follow                         → CollectionController

Vue (front/src/)
  NotificationsPage.vue (page) → useQuery(getArticles) → ArticleCard.vue (molecule)
  MangaDetailPage.vue (page)   → useMutation(toggleFollow)
```

---

## Backend Steps

### Step 1 — Domain: Add `notificationsEnabled` + `lastNotifiedAt` to `CollectionEntry`

**File:** `back/src/Collection/Domain/CollectionEntry.php` *(modify)*
**Why:** Opt-in flag controls which entries are crawled; `lastNotifiedAt` prevents email flooding.

```php
// Add these two constructor parameters (with defaults so existing code compiles)
#[ORM\Column]
public bool $notificationsEnabled = false,

#[ORM\Column(nullable: true)]
public ?\DateTimeImmutable $lastNotifiedAt = null,
```

Also update `toArray()` to expose `notificationsEnabled`:

```php
'notificationsEnabled' => $this->notificationsEnabled,
```

> Both fields have defaults so no existing constructor call breaks.

---

### Step 2 — Domain: Create `Article` entity

**File:** `back/src/Notification/Domain/Article.php` *(create)*
**Why:** Stores each article found by a crawl job, linked to the CollectionEntry it was found for.

```php
<?php

declare(strict_types=1);

namespace App\Notification\Domain;

use App\Collection\Domain\CollectionEntry;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'articles')]
#[ORM\UniqueConstraint(name: 'uniq_article_entry_url', columns: ['collection_entry_id', 'url'])]
class Article
{
    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public readonly string $id,

        #[ORM\ManyToOne(targetEntity: CollectionEntry::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public readonly CollectionEntry $collectionEntry,

        #[ORM\Column(length: 500)]
        public readonly string $title,

        #[ORM\Column(type: 'text')]
        public readonly string $url,

        #[ORM\Column(length: 100)]
        public readonly string $sourceName,

        #[ORM\Column(length: 255, nullable: true)]
        public readonly ?string $author,

        #[ORM\Column(type: 'text', nullable: true)]
        public readonly ?string $imageUrl,

        #[ORM\Column(nullable: true)]
        public readonly ?\DateTimeImmutable $publishedAt,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'collectionEntry' => [
                'id'    => $this->collectionEntry->id,
                'manga' => [
                    'id'       => $this->collectionEntry->manga->id,
                    'title'    => $this->collectionEntry->manga->title,
                    'coverUrl' => $this->collectionEntry->manga->coverUrl,
                ],
            ],
            'title'       => $this->title,
            'url'         => $this->url,
            'sourceName'  => $this->sourceName,
            'author'      => $this->author,
            'imageUrl'    => $this->imageUrl,
            'publishedAt' => $this->publishedAt?->format(\DateTimeInterface::ATOM),
            'createdAt'   => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
```

---

### Step 3 — Domain: Create `ActivityLog` entity

**File:** `back/src/Notification/Domain/ActivityLog.php` *(create)*
**Why:** Records every crawl job (success/error, new article count) for the activity journal.

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

        #[ORM\ManyToOne(targetEntity: CollectionEntry::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public readonly CollectionEntry $collectionEntry,

        /** 'rss' | 'jikan' */
        #[ORM\Column(length: 20)]
        public readonly string $sourceType,

        #[ORM\Column(length: 100)]
        public readonly string $sourceName,

        /** 'running' | 'success' | 'error' */
        #[ORM\Column(length: 20)]
        public string $status = 'running',

        #[ORM\Column(type: 'text', nullable: true)]
        public ?string $errorMessage = null,

        #[ORM\Column(nullable: true)]
        public ?int $newArticlesCount = null,
    ) {
        $this->startedAt = new \DateTimeImmutable();
    }

    public function markSuccess(int $newArticlesCount): void
    {
        $this->status          = 'success';
        $this->newArticlesCount = $newArticlesCount;
        $this->finishedAt      = new \DateTimeImmutable();
    }

    public function markError(string $message): void
    {
        $this->status       = 'error';
        $this->errorMessage = $message;
        $this->finishedAt   = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'collectionEntryId' => $this->collectionEntry->id,
            'mangaTitle'       => $this->collectionEntry->manga->title,
            'sourceType'       => $this->sourceType,
            'sourceName'       => $this->sourceName,
            'status'           => $this->status,
            'errorMessage'     => $this->errorMessage,
            'newArticlesCount' => $this->newArticlesCount,
            'startedAt'        => $this->startedAt->format(\DateTimeInterface::ATOM),
            'finishedAt'       => $this->finishedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
```

---

### Step 4 — Domain: Create `ArticleRepositoryInterface`

**File:** `back/src/Notification/Domain/ArticleRepositoryInterface.php` *(create)*
**Why:** Hexagonal boundary — application and infrastructure depend on this interface, not Doctrine.

```php
<?php

declare(strict_types=1);

namespace App\Notification\Domain;

interface ArticleRepositoryInterface
{
    public function existsByCollectionEntryAndUrl(string $collectionEntryId, string $url): bool;

    public function save(Article $article): void;

    /**
     * @return array{items: Article[], total: int}
     */
    public function findPaginated(int $page, int $limit, ?string $collectionEntryId): array;
}
```

---

### Step 5 — Domain: Create `ActivityLogRepositoryInterface`

**File:** `back/src/Notification/Domain/ActivityLogRepositoryInterface.php` *(create)*
**Why:** Hexagonal boundary for the crawl journal.

```php
<?php

declare(strict_types=1);

namespace App\Notification\Domain;

interface ActivityLogRepositoryInterface
{
    public function save(ActivityLog $log): void;

    /** @return ActivityLog[] */
    public function findRecent(int $limit = 50): array;
}
```

---

### Step 6 — Domain: Update `CollectionRepositoryInterface`

**File:** `back/src/Collection/Domain/CollectionRepositoryInterface.php` *(modify)*
**Why:** The scheduler needs to load only followed entries to fan out crawl jobs.

```php
// Add this method to the existing interface:

/** @return CollectionEntry[] Only entries with notificationsEnabled = true */
public function findFollowed(): array;
```

---

### Step 7 — Infrastructure: Create `DoctrineArticleRepository`

**File:** `back/src/Notification/Infrastructure/Doctrine/DoctrineArticleRepository.php` *(create)*
**Why:** Doctrine implementation of `ArticleRepositoryInterface`.

```php
<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Doctrine;

use App\Notification\Domain\Article;
use App\Notification\Domain\ArticleRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineArticleRepository implements ArticleRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function existsByCollectionEntryAndUrl(string $collectionEntryId, string $url): bool
    {
        return $this->em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Article::class, 'a')
            ->where('a.collectionEntry = :ceId')
            ->andWhere('a.url = :url')
            ->setParameter('ceId', $collectionEntryId)
            ->setParameter('url', $url)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function save(Article $article): void
    {
        $this->em->persist($article);
        $this->em->flush();
    }

    public function findPaginated(int $page, int $limit, ?string $collectionEntryId): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Article::class, 'a')
            ->orderBy('a.createdAt', 'DESC');

        if ($collectionEntryId !== null) {
            $qb->where('a.collectionEntry = :ceId')
               ->setParameter('ceId', $collectionEntryId);
        }

        $total = (clone $qb)->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => (int) $total];
    }
}
```

---

### Step 8 — Infrastructure: Create `DoctrineActivityLogRepository`

**File:** `back/src/Notification/Infrastructure/Doctrine/DoctrineActivityLogRepository.php` *(create)*
**Why:** Doctrine implementation of `ActivityLogRepositoryInterface`.

```php
<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Doctrine;

use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineActivityLogRepository implements ActivityLogRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(ActivityLog $log): void
    {
        $this->em->persist($log);
        $this->em->flush();
    }

    public function findRecent(int $limit = 50): array
    {
        return $this->em->createQueryBuilder()
            ->select('l')
            ->from(ActivityLog::class, 'l')
            ->orderBy('l.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
```

---

### Step 9 — Infrastructure: Implement `findFollowed()` in `DoctrineCollectionRepository`

**File:** `back/src/Collection/Infrastructure/Doctrine/DoctrineCollectionRepository.php` *(modify)*
**Why:** Required by the scheduler to know which entries to crawl.

```php
// Add this method to the existing class:

public function findFollowed(): array
{
    return $this->em->createQueryBuilder()
        ->select('e')
        ->from(CollectionEntry::class, 'e')
        ->where('e.notificationsEnabled = true')
        ->getQuery()
        ->getResult();
}
```

---

### Step 10 — Application: `ToggleFollowCommand` + `ToggleFollowHandler`

**File:** `back/src/Collection/Application/ToggleFollow/ToggleFollowCommand.php` *(create)*

```php
<?php

declare(strict_types=1);

namespace App\Collection\Application\ToggleFollow;

final readonly class ToggleFollowCommand
{
    public function __construct(public string $collectionEntryId) {}
}
```

**File:** `back/src/Collection/Application/ToggleFollow/ToggleFollowHandler.php` *(create)*
**Why:** Flips `notificationsEnabled` on the CollectionEntry and persists.

```php
<?php

declare(strict_types=1);

namespace App\Collection\Application\ToggleFollow;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ToggleFollowHandler
{
    public function __construct(private CollectionRepositoryInterface $repository) {}

    public function __invoke(ToggleFollowCommand $command): bool
    {
        $entry = $this->repository->findById($command->collectionEntryId);
        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $command->collectionEntryId);
        }

        $entry->notificationsEnabled = !$entry->notificationsEnabled;
        $this->repository->save($entry);

        return $entry->notificationsEnabled;
    }
}
```

> Returns the new boolean state so the controller can echo it.

---

### Step 11 — Application: `GetArticlesQuery` + `GetArticlesHandler`

**File:** `back/src/Notification/Application/GetArticles/GetArticlesQuery.php` *(create)*

```php
<?php

declare(strict_types=1);

namespace App\Notification\Application\GetArticles;

final readonly class GetArticlesQuery
{
    public function __construct(
        public int $page = 1,
        public int $limit = 12,
        public ?string $collectionEntryId = null,
    ) {}
}
```

**File:** `back/src/Notification/Application/GetArticles/GetArticlesHandler.php` *(create)*

```php
<?php

declare(strict_types=1);

namespace App\Notification\Application\GetArticles;

use App\Notification\Domain\ArticleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetArticlesHandler
{
    public function __construct(private ArticleRepositoryInterface $repository) {}

    /** @return array{items: array<int, array<string, mixed>>, total: int, page: int, limit: int, totalPages: int} */
    public function __invoke(GetArticlesQuery $query): array
    {
        $result = $this->repository->findPaginated($query->page, $query->limit, $query->collectionEntryId);

        return [
            'items'      => array_map(static fn ($a) => $a->toArray(), $result['items']),
            'total'      => $result['total'],
            'page'       => $query->page,
            'limit'      => $query->limit,
            'totalPages' => (int) ceil($result['total'] / $query->limit),
        ];
    }
}
```

---

### Step 12 — Application: `GetActivityLogsQuery` + `GetActivityLogsHandler`

**File:** `back/src/Notification/Application/GetActivityLogs/GetActivityLogsQuery.php` *(create)*

```php
<?php

declare(strict_types=1);

namespace App\Notification\Application\GetActivityLogs;

final readonly class GetActivityLogsQuery
{
    public function __construct(public int $limit = 50) {}
}
```

**File:** `back/src/Notification/Application/GetActivityLogs/GetActivityLogsHandler.php` *(create)*

```php
<?php

declare(strict_types=1);

namespace App\Notification\Application\GetActivityLogs;

use App\Notification\Domain\ActivityLogRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetActivityLogsHandler
{
    public function __construct(private ActivityLogRepositoryInterface $repository) {}

    /** @return array<int, array<string, mixed>> */
    public function __invoke(GetActivityLogsQuery $query): array
    {
        return array_map(
            static fn ($l) => $l->toArray(),
            $this->repository->findRecent($query->limit),
        );
    }
}
```

---

### Step 13 — Application: `FetchRssFeedMessage` + `FetchRssFeedHandler`

**File:** `back/src/Notification/Application/Fetch/FetchRssFeedMessage.php` *(create)*
**Why:** One async Messenger message per RSS feed per followed manga — allows full parallelism.

```php
<?php

declare(strict_types=1);

namespace App\Notification\Application\Fetch;

final readonly class FetchRssFeedMessage
{
    public function __construct(
        public string $collectionEntryId,
        public string $mangaTitle,
        public string $feedName,
        public string $feedUrl,
    ) {}
}
```

**File:** `back/src/Notification/Application/Fetch/FetchRssFeedHandler.php` *(create)*
**Why:** Fetches one RSS feed, filters items mentioning the manga title, saves new Articles, and writes an ActivityLog.

```php
<?php

declare(strict_types=1);

namespace App\Notification\Application\Fetch;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Domain\Article;
use App\Notification\Domain\ArticleRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final readonly class FetchRssFeedHandler
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CollectionRepositoryInterface $collectionRepository,
        private ArticleRepositoryInterface $articleRepository,
        private ActivityLogRepositoryInterface $activityLogRepository,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(FetchRssFeedMessage $message): void
    {
        $entry = $this->collectionRepository->findById($message->collectionEntryId);
        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $message->collectionEntryId);
        }

        $log = new ActivityLog(
            id: Uuid::v4()->toRfc4122(),
            collectionEntry: $entry,
            sourceType: 'rss',
            sourceName: $message->feedName,
        );
        $this->activityLogRepository->save($log);

        try {
            $response = $this->httpClient->request('GET', $message->feedUrl, [
                'timeout' => 10,
                'headers' => ['User-Agent' => 'Ziggytheque/1.0 (manga tracker)'],
            ]);
            $xml = new \SimpleXMLElement($response->getContent());

            $newCount  = 0;
            $keywords  = array_filter(array_map('trim', explode(' ', mb_strtolower($message->mangaTitle))));
            $channel   = $xml->channel ?? $xml; // RSS 2.0 vs Atom

            foreach ($channel->item ?? [] as $item) {
                $itemTitle = (string) ($item->title ?? '');
                $itemDesc  = strip_tags((string) ($item->description ?? ''));
                $itemUrl   = (string) ($item->link ?? $item->guid ?? '');
                $itemDate  = (string) ($item->pubDate ?? $item->children('dc', true)->date ?? '');

                $haystack = mb_strtolower($itemTitle . ' ' . $itemDesc);
                $matches  = array_filter($keywords, static fn (string $k) => mb_strlen($k) > 3 && str_contains($haystack, $k));

                if (empty($matches) || empty($itemUrl)) {
                    continue;
                }

                if ($this->articleRepository->existsByCollectionEntryAndUrl($entry->id, $itemUrl)) {
                    continue;
                }

                // Extract image from <media:content> or <enclosure> or og:image in description
                $imageUrl = $this->extractImage($item);

                $publishedAt = $itemDate !== '' ? \DateTimeImmutable::createFromFormat(\DateTimeInterface::RSS, $itemDate) ?: null : null;

                $article = new Article(
                    id: Uuid::v4()->toRfc4122(),
                    collectionEntry: $entry,
                    title: mb_substr($itemTitle, 0, 500),
                    url: $itemUrl,
                    sourceName: $message->feedName,
                    author: null,
                    imageUrl: $imageUrl,
                    publishedAt: $publishedAt ?: null,
                );
                $this->articleRepository->save($article);
                ++$newCount;
            }

            $log->markSuccess($newCount);
            $this->activityLogRepository->save($log);
            $this->logger->info('RSS feed fetched', ['feed' => $message->feedName, 'manga' => $message->mangaTitle, 'new' => $newCount]);
        } catch (\Throwable $e) {
            $log->markError($e->getMessage());
            $this->activityLogRepository->save($log);
            $this->logger->error('RSS feed failed', ['feed' => $message->feedName, 'error' => $e->getMessage()]);
            throw $e; // re-throw so Messenger retry strategy applies
        }
    }

    private function extractImage(\SimpleXMLElement $item): ?string
    {
        // <media:content url="...">
        $media = $item->children('media', true);
        if (isset($media->content)) {
            $url = (string) $media->content->attributes()['url'];
            if ($url !== '') {
                return $url;
            }
        }

        // <enclosure url="..." type="image/...">
        if (isset($item->enclosure)) {
            $type = (string) $item->enclosure->attributes()['type'];
            if (str_starts_with($type, 'image/')) {
                return (string) $item->enclosure->attributes()['url'];
            }
        }

        // Attempt to extract first <img> from description HTML
        $desc = (string) ($item->description ?? '');
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $desc, $m)) {
            return $m[1];
        }

        return null;
    }
}
```

---

### Step 14 — Application: `FetchJikanNewsMessage` + `FetchJikanNewsHandler`

**File:** `back/src/Notification/Application/Fetch/FetchJikanNewsMessage.php` *(create)*
**Why:** One async job per followed manga that has a Jikan `externalId` (= MAL ID).

```php
<?php

declare(strict_types=1);

namespace App\Notification\Application\Fetch;

final readonly class FetchJikanNewsMessage
{
    public function __construct(
        public string $collectionEntryId,
        public string $mangaTitle,
        public string $malId,
    ) {}
}
```

**File:** `back/src/Notification/Application/Fetch/FetchJikanNewsHandler.php` *(create)*
**Why:** Calls `GET https://api.jikan.moe/v4/manga/{malId}/news`, saves new articles, logs outcome.

```php
<?php

declare(strict_types=1);

namespace App\Notification\Application\Fetch;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Domain\Article;
use App\Notification\Domain\ArticleRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final readonly class FetchJikanNewsHandler
{
    private const BASE_URL = 'https://api.jikan.moe/v4';

    public function __construct(
        private HttpClientInterface $httpClient,
        private CollectionRepositoryInterface $collectionRepository,
        private ArticleRepositoryInterface $articleRepository,
        private ActivityLogRepositoryInterface $activityLogRepository,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(FetchJikanNewsMessage $message): void
    {
        $entry = $this->collectionRepository->findById($message->collectionEntryId);
        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $message->collectionEntryId);
        }

        $log = new ActivityLog(
            id: Uuid::v4()->toRfc4122(),
            collectionEntry: $entry,
            sourceType: 'jikan',
            sourceName: 'jikan-news',
        );
        $this->activityLogRepository->save($log);

        try {
            $response = $this->httpClient->request(
                'GET',
                self::BASE_URL . '/manga/' . $message->malId . '/news',
                ['timeout' => 10],
            );
            $data = $response->toArray();
            $items = $data['data'] ?? [];

            $newCount = 0;
            foreach ($items as $item) {
                $url = $item['url'] ?? null;
                if ($url === null) {
                    continue;
                }

                if ($this->articleRepository->existsByCollectionEntryAndUrl($entry->id, $url)) {
                    continue;
                }

                $publishedAt = isset($item['date'])
                    ? new \DateTimeImmutable($item['date'])
                    : null;

                $article = new Article(
                    id: Uuid::v4()->toRfc4122(),
                    collectionEntry: $entry,
                    title: mb_substr((string) ($item['title'] ?? 'Jikan News'), 0, 500),
                    url: $url,
                    sourceName: 'jikan-news',
                    author: $item['author_username'] ?? null,
                    imageUrl: $item['images']['jpg']['image_url'] ?? null,
                    publishedAt: $publishedAt,
                );
                $this->articleRepository->save($article);
                ++$newCount;
            }

            $log->markSuccess($newCount);
            $this->activityLogRepository->save($log);
            $this->logger->info('Jikan news fetched', ['malId' => $message->malId, 'manga' => $message->mangaTitle, 'new' => $newCount]);
        } catch (\Throwable $e) {
            $log->markError($e->getMessage());
            $this->activityLogRepository->save($log);
            $this->logger->error('Jikan news failed', ['malId' => $message->malId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
```

---

### Step 15 — Application: `SendFollowingNotificationMessage` + `SendFollowingNotificationHandler`

**File:** `back/src/Notification/Application/Email/SendFollowingNotificationMessage.php` *(create)*
**Why:** Decouples email sending from crawl jobs — dispatched when new articles are found.

```php
<?php

declare(strict_types=1);

namespace App\Notification\Application\Email;

final readonly class SendFollowingNotificationMessage
{
    public function __construct(
        public string $collectionEntryId,
        /** @var string[] */
        public array $articleIds,
    ) {}
}
```

**File:** `back/src/Notification/Application/Email/SendFollowingNotificationHandler.php` *(create)*
**Why:** Checks cooldown (12h), then sends a styled HTML email via Symfony Mailer.

```php
<?php

declare(strict_types=1);

namespace App\Notification\Application\Email;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\ArticleRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsMessageHandler]
final readonly class SendFollowingNotificationHandler
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private ArticleRepositoryInterface $articleRepository,
        private MailerInterface $mailer,
        private Environment $twig,
        private LoggerInterface $logger,
        private string $notificationEmail,
    ) {}

    public function __invoke(SendFollowingNotificationMessage $message): void
    {
        $entry = $this->collectionRepository->findById($message->collectionEntryId);
        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $message->collectionEntryId);
        }

        // Cooldown: do not send if an email was sent in the last 12 hours
        if (
            $entry->lastNotifiedAt !== null
            && $entry->lastNotifiedAt > new \DateTimeImmutable('-12 hours')
        ) {
            $this->logger->info('Email skipped (cooldown)', ['manga' => $entry->manga->title]);
            return;
        }

        $result = $this->articleRepository->findPaginated(1, 10, $message->collectionEntryId);
        $articles = $result['items'];

        if (empty($articles)) {
            return;
        }

        $html = $this->twig->render('emails/new_articles_notification.html.twig', [
            'manga'    => $entry->manga,
            'articles' => $articles,
        ]);

        $email = (new Email())
            ->from('ziggytheque@noreply.local')
            ->to($this->notificationEmail)
            ->subject('📰 Nouveautés : ' . $entry->manga->title)
            ->html($html);

        $this->mailer->send($email);

        $entry->lastNotifiedAt = new \DateTimeImmutable();
        $this->collectionRepository->save($entry);

        $this->logger->info('Notification email sent', ['manga' => $entry->manga->title]);
    }
}
```

> `$notificationEmail` is injected via `services.yaml` (see Step 20).

---

### Step 16 — Application: `DispatchFollowingCrawlTask` (Symfony Scheduler)

**File:** `back/src/Notification/Application/Schedule/DispatchFollowingCrawlTask.php` *(create)*
**Why:** Entry point called by Symfony Scheduler twice daily; fans out one message per source per followed manga.

```php
<?php

declare(strict_types=1);

namespace App\Notification\Application\Schedule;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Application\Fetch\FetchJikanNewsMessage;
use App\Notification\Application\Fetch\FetchRssFeedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

/**
 * Runs at 07:00 and 19:00 every day.
 * Dispatches async crawl jobs (one per source per followed manga).
 */
#[AsCronTask('0 7,19 * * *')]
final readonly class DispatchFollowingCrawlTask
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        /** @var array<int, array{name: string, url: string}> */
        private array $rssFeeds,
    ) {}

    public function __invoke(): void
    {
        $followed = $this->collectionRepository->findFollowed();

        if (empty($followed)) {
            $this->logger->info('FollowingCrawl: no followed entries, skipping.');
            return;
        }

        $totalJobs = 0;

        foreach ($followed as $entry) {
            // One job per RSS feed
            foreach ($this->rssFeeds as $feed) {
                $this->messageBus->dispatch(new FetchRssFeedMessage(
                    collectionEntryId: $entry->id,
                    mangaTitle: $entry->manga->title,
                    feedName: $feed['name'],
                    feedUrl: $feed['url'],
                ));
                ++$totalJobs;
            }

            // One Jikan job if the manga has a MAL external ID
            if ($entry->manga->externalId !== null) {
                $this->messageBus->dispatch(new FetchJikanNewsMessage(
                    collectionEntryId: $entry->id,
                    mangaTitle: $entry->manga->title,
                    malId: $entry->manga->externalId,
                ));
                ++$totalJobs;
            }
        }

        $this->logger->info('FollowingCrawl dispatched', [
            'followed' => count($followed),
            'jobs'     => $totalJobs,
        ]);
    }
}
```

> `$rssFeeds` is injected as a service binding (see Step 20). The `MessageBusInterface` here is the default bus (= `command.bus`). Because the messages are routed to `async`, they are processed by the worker — not synchronously.

---

### Step 17 — Infrastructure: `ArticleController`

**File:** `back/src/Notification/Infrastructure/Http/ArticleController.php` *(create)*
**Why:** Exposes paginated article feed and activity log to the frontend.

```php
<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Http;

use App\Notification\Application\GetActivityLogs\GetActivityLogsQuery;
use App\Notification\Application\GetArticles\GetArticlesQuery;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/articles')]
final readonly class ArticleController
{
    public function __construct(private QueryBusInterface $queryBus) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page              = max(1, (int) $request->query->get('page', 1));
        $limit             = min(50, max(1, (int) $request->query->get('limit', 12)));
        $collectionEntryId = $request->query->get('collectionEntryId') ?: null;

        return new JsonResponse(
            $this->queryBus->ask(new GetArticlesQuery($page, $limit, $collectionEntryId)),
        );
    }

    #[Route('/activity-logs', methods: ['GET'])]
    public function activityLogs(Request $request): JsonResponse
    {
        $limit = min(200, max(1, (int) $request->query->get('limit', 50)));

        return new JsonResponse(
            $this->queryBus->ask(new GetActivityLogsQuery($limit)),
        );
    }
}
```

---

### Step 18 — Infrastructure: Add follow toggle to `CollectionController`

**File:** `back/src/Collection/Infrastructure/Http/CollectionController.php` *(modify)*
**Why:** Exposes the follow toggle as `PATCH /api/collection/{id}/follow`.

```php
// Add this import at the top:
use App\Collection\Application\ToggleFollow\ToggleFollowCommand;

// Add this action in the class body:
#[Route('/{id}/follow', methods: ['PATCH'])]
public function toggleFollow(string $id): JsonResponse
{
    $enabled = $this->commandBus->dispatch(new ToggleFollowCommand($id));

    return new JsonResponse(['notificationsEnabled' => $enabled]);
}
```

---

### Step 19 — Infrastructure: Email Twig template

**File:** `back/templates/emails/new_articles_notification.html.twig` *(create)*
**Why:** HTML email sent when new articles are found for a followed manga.

```twig
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Nouvelles sur {{ manga.title }}</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; padding: 0; }
    .container { max-width: 600px; margin: 32px auto; padding: 0 16px; }
    .header { text-align: center; padding: 32px 0 16px; }
    .header img { width: 80px; height: 120px; object-fit: cover; border-radius: 8px; }
    .header h1 { font-size: 22px; margin: 16px 0 4px; color: #f1f5f9; }
    .header p  { color: #94a3b8; font-size: 13px; margin: 0; }
    .articles { margin-top: 24px; }
    .article { background: #1e293b; border-radius: 12px; overflow: hidden; margin-bottom: 16px; display: flex; gap: 0; }
    .article-image { width: 120px; min-height: 80px; flex-shrink: 0; background: #334155; }
    .article-image img { width: 120px; height: 100%; object-fit: cover; display: block; }
    .article-body { padding: 14px 16px; flex: 1; }
    .article-source { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; margin-bottom: 4px; }
    .article-title { font-size: 14px; font-weight: 600; color: #f1f5f9; line-height: 1.4; margin: 0 0 6px; }
    .article-author { font-size: 11px; color: #94a3b8; margin-bottom: 10px; }
    .article-link { display: inline-block; font-size: 12px; background: #6366f1; color: #fff; padding: 5px 12px; border-radius: 6px; text-decoration: none; }
    .footer { text-align: center; margin-top: 32px; padding-bottom: 32px; font-size: 11px; color: #475569; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      {% if manga.coverUrl %}
        <img src="{{ manga.coverUrl }}" alt="{{ manga.title }}" />
      {% endif %}
      <h1>📰 Nouveautés — {{ manga.title }}</h1>
      <p>{{ articles|length }} nouvel{{ articles|length > 1 ? 'les actualités ont été trouvées' : 'le actualité a été trouvée' }}</p>
    </div>

    <div class="articles">
      {% for article in articles %}
        <div class="article">
          {% if article.imageUrl %}
            <div class="article-image">
              <img src="{{ article.imageUrl }}" alt="{{ article.title }}" />
            </div>
          {% endif %}
          <div class="article-body">
            <div class="article-source">{{ article.sourceName }}</div>
            <p class="article-title">{{ article.title }}</p>
            {% if article.author %}
              <p class="article-author">par {{ article.author }}</p>
            {% endif %}
            <a href="{{ article.url }}" class="article-link">Lire l'article →</a>
          </div>
        </div>
      {% endfor %}
    </div>

    <div class="footer">
      Ziggytheque — vous suivez {{ manga.title }}<br />
      <small>Pour arrêter les notifications, désactivez le suivi depuis l'application.</small>
    </div>
  </div>
</body>
</html>
```

> Note: `article` here is the array returned by `Article::toArray()`, not the entity directly.
> The template receives `articles` as an array of raw arrays.

---

### Step 20 — Config: Install packages, update messenger.yaml, services.yaml, docker-compose.yml

#### 20a — Install Symfony Scheduler and Mailer

```bash
docker compose exec back composer require symfony/scheduler symfony/mailer
```

#### 20b — Update `back/config/packages/messenger.yaml`

Route the new async messages and add the `scheduler_default` transport:

```yaml
framework:
    messenger:
        # ... existing config ...
        transports:
            async:
                # ... existing ...
            failed: 'doctrine://default?queue_name=failed'
            sync: 'sync://'
            scheduler_default:
                dsn: 'doctrine://default?queue_name=scheduler_default&auto_setup=0'

        routing:
            # ... existing routes ...
            App\Notification\Application\Fetch\FetchRssFeedMessage: async
            App\Notification\Application\Fetch\FetchJikanNewsMessage: async
            App\Notification\Application\Email\SendFollowingNotificationMessage: async
```

#### 20c — Update `back/config/services.yaml`

```yaml
parameters:
    # ... existing ...
    notification_email: '%env(NOTIFICATION_EMAIL)%'
    following.rss_feeds:
        - { name: 'manga-news', url: 'https://www.manga-news.com/index.php/feed/' }
        - { name: 'nautiljon', url: 'https://www.nautiljon.com/rss.xml' }
        - { name: 'anime-kun', url: 'https://www.anime-kun.net/feed/' }
        - { name: 'animotaku', url: 'https://animotaku.fr/feed/' }

services:
    # ... existing ...

    App\Notification\Domain\ArticleRepositoryInterface:
        alias: App\Notification\Infrastructure\Doctrine\DoctrineArticleRepository

    App\Notification\Domain\ActivityLogRepositoryInterface:
        alias: App\Notification\Infrastructure\Doctrine\DoctrineActivityLogRepository

    App\Notification\Application\Schedule\DispatchFollowingCrawlTask:
        arguments:
            $rssFeeds: '%following.rss_feeds%'

    App\Notification\Application\Email\SendFollowingNotificationHandler:
        arguments:
            $notificationEmail: '%notification_email%'
```

#### 20d — Add `NOTIFICATION_EMAIL` + `MAILER_DSN` to `back/.env`

```dotenv
MAILER_DSN=smtp://mailer:1025
NOTIFICATION_EMAIL=you@example.com
```

#### 20e — Update `docker-compose.yml` worker command to include scheduler

```yaml
worker:
    # ...
    command: php bin/console messenger:consume async scheduler_default --time-limit=3600 -vv
    environment:
        APP_ENV: dev
        DATABASE_URL: postgresql://ziggy:ziggy@db:5432/ziggytheque?serverVersion=17&charset=utf8
        MESSENGER_TRANSPORT_DSN: doctrine://default?auto_setup=0
        MAILER_DSN: smtp://mailer:1025
        NOTIFICATION_EMAIL: you@example.com
```

Also add the same env vars to the `back` service.

---

## Database Migration

**Why:** Adds `articles`, `activity_logs` tables; adds `notifications_enabled` and `last_notified_at` columns to `collection_entries`.

```bash
# Generate migration (always inside the container)
docker compose exec back php bin/console doctrine:migrations:diff

# Review the generated file in back/migrations/VersionYYYYMMDDHHmmss.php
# Expected SQL:
#   ALTER TABLE collection_entries ADD notifications_enabled BOOLEAN NOT NULL DEFAULT FALSE
#   ALTER TABLE collection_entries ADD last_notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
#   CREATE TABLE articles (...)
#   CREATE TABLE activity_logs (...)

# Apply
docker compose exec back php bin/console doctrine:migrations:migrate --no-interaction
```

> Also auto-setup the scheduler transport table:
```bash
docker compose exec back php bin/console messenger:setup-transports
```

---

## Frontend Steps

### Step 21 — `front/src/types/index.ts`: Add new types, update `CollectionEntry`

**File:** `front/src/types/index.ts` *(modify)*
**Why:** TypeScript contracts for all new API shapes.

```typescript
// Modify CollectionEntry — add field:
export interface CollectionEntry {
  // ... existing fields ...
  notificationsEnabled: boolean
}

// Add new types:
export interface ArticleCollectionEntry {
  id: string
  manga: {
    id: string
    title: string
    coverUrl: string | null
  }
}

export interface Article {
  id: string
  collectionEntry: ArticleCollectionEntry
  title: string
  url: string
  sourceName: string
  author: string | null
  imageUrl: string | null
  publishedAt: string | null
  createdAt: string
}

export interface ArticlePage {
  items: Article[]
  total: number
  page: number
  limit: number
  totalPages: number
}

export interface ActivityLog {
  id: string
  collectionEntryId: string
  mangaTitle: string
  sourceType: 'rss' | 'jikan'
  sourceName: string
  status: 'running' | 'success' | 'error'
  errorMessage: string | null
  newArticlesCount: number | null
  startedAt: string
  finishedAt: string | null
}
```

---

### Step 22 — `front/src/api/notification.ts`: Rewrite to add articles + activity-log calls

**File:** `front/src/api/notification.ts` *(modify)*
**Why:** New endpoints replace the stub implementation.

```typescript
import client from './client'
import type { Article, ArticlePage, ActivityLog } from '@/types'

export interface ArticlesParams {
  page?: number
  limit?: number
  collectionEntryId?: string
}

export async function getArticles(params: ArticlesParams = {}): Promise<ArticlePage> {
  const res = await client.get('/articles', { params })
  return res.data
}

export async function getActivityLogs(limit = 50): Promise<ActivityLog[]> {
  const res = await client.get('/articles/activity-logs', { params: { limit } })
  return res.data
}

// Keep old stubs for backward compat (old NotificationsPage referenced these)
export async function getNotifications() {
  return []
}

export async function markNotificationRead(_id: string): Promise<void> {}
```

---

### Step 23 — `front/src/api/collection.ts`: Add `toggleFollow`

**File:** `front/src/api/collection.ts` *(modify)*
**Why:** Calls `PATCH /api/collection/{id}/follow`.

```typescript
// Add at the end of the file:
export async function toggleFollow(id: string): Promise<{ notificationsEnabled: boolean }> {
  const res = await client.patch(`/collection/${id}/follow`)
  return res.data
}
```

---

### Step 24 — Create `ArticleCard.vue` molecule

**File:** `front/src/components/molecules/ArticleCard.vue` *(create)*
**Why:** Reusable card for a single article; organisms/pages pass data as props.

```vue
<script setup lang="ts">
import type { Article } from '@/types'

defineProps<{ article: Article }>()
</script>

<template>
  <a
    :href="article.url"
    target="_blank"
    rel="noopener noreferrer"
    class="card card-side bg-base-200 shadow hover:shadow-lg transition-shadow duration-200 overflow-hidden group"
  >
    <!-- Preview image -->
    <figure class="w-28 sm:w-36 shrink-0 bg-base-300">
      <img
        v-if="article.imageUrl"
        :src="article.imageUrl"
        :alt="article.title"
        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
        loading="lazy"
      />
      <div v-else class="w-full h-full flex items-center justify-center text-base-content/20">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 12h6m-6-4h4" />
        </svg>
      </div>
    </figure>

    <div class="card-body py-4 px-5 gap-1.5">
      <!-- Manga badge + source -->
      <div class="flex items-center gap-2 flex-wrap">
        <div class="flex items-center gap-1.5">
          <img
            v-if="article.collectionEntry.manga.coverUrl"
            :src="article.collectionEntry.manga.coverUrl"
            :alt="article.collectionEntry.manga.title"
            class="w-5 h-7 object-cover rounded-sm shrink-0"
          />
          <span class="text-xs font-semibold text-primary truncate max-w-32">
            {{ article.collectionEntry.manga.title }}
          </span>
        </div>
        <span class="badge badge-ghost badge-xs text-base-content/50">{{ article.sourceName }}</span>
      </div>

      <!-- Title -->
      <h3 class="text-sm font-bold leading-snug line-clamp-2 text-base-content group-hover:text-primary transition-colors">
        {{ article.title }}
      </h3>

      <!-- Author + date -->
      <div class="flex items-center gap-3 text-[11px] text-base-content/40 mt-1">
        <span v-if="article.author">par {{ article.author }}</span>
        <span v-if="article.publishedAt">
          {{ new Date(article.publishedAt).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }) }}
        </span>
      </div>
    </div>
  </a>
</template>
```

---

### Step 25 — Rewrite `NotificationsPage.vue`

**File:** `front/src/pages/NotificationsPage.vue` *(full rewrite)*
**Why:** The old stub is replaced with a proper card feed with filter, pagination, and activity log tab.

```vue
<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { getArticles } from '@/api/notification'
import { getCollection, toggleFollow } from '@/api/collection'
import { useI18n } from 'vue-i18n'
import ArticleCard from '@/components/molecules/ArticleCard.vue'
import type { CollectionEntry } from '@/types'

const { t } = useI18n()
const qc = useQueryClient()

const page = ref(1)
const limit = 12
const selectedCollectionId = ref<string | undefined>(undefined)

// Load all collection entries to populate the filter
const { data: collection } = useQuery({
  queryKey: ['collection'],
  queryFn: getCollection,
})

// Only show followed entries in the filter
const followedEntries = computed<CollectionEntry[]>(
  () => collection.value?.filter((e) => e.notificationsEnabled) ?? [],
)

// Articles query — re-fetches on page / filter change
const { data: articlePage, isPending } = useQuery({
  queryKey: computed(() => ['articles', page.value, selectedCollectionId.value]),
  queryFn: () => getArticles({ page: page.value, limit, collectionEntryId: selectedCollectionId.value }),
})

// Reset page when filter changes
watch(selectedCollectionId, () => { page.value = 1 })

const toggleFollowMutation = useMutation({
  mutationFn: (id: string) => toggleFollow(id),
  onSuccess: () => qc.invalidateQueries({ queryKey: ['collection'] }),
})
</script>

<template>
  <div class="min-h-screen">
    <!-- Header -->
    <div class="bg-gradient-to-br from-secondary/10 via-base-100 to-base-100 border-b border-base-200 px-6 py-8">
      <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-extrabold tracking-tight">{{ t('notifications.title') }}</h1>
        <p class="text-base-content/50 text-sm mt-1">{{ t('notifications.subtitle') }}</p>
      </div>
    </div>

    <div class="max-w-4xl mx-auto px-6 py-8 space-y-6">
      <!-- Filter bar -->
      <div class="flex items-center gap-3 flex-wrap">
        <span class="text-sm text-base-content/60 shrink-0">{{ t('notifications.filterBy') }}</span>
        <button
          class="btn btn-sm"
          :class="selectedCollectionId === undefined ? 'btn-primary' : 'btn-ghost'"
          @click="selectedCollectionId = undefined"
        >
          {{ t('notifications.allMangas') }}
        </button>
        <button
          v-for="entry in followedEntries"
          :key="entry.id"
          class="btn btn-sm gap-1.5"
          :class="selectedCollectionId === entry.id ? 'btn-primary' : 'btn-ghost'"
          @click="selectedCollectionId = entry.id"
        >
          <img
            v-if="entry.manga.coverUrl"
            :src="entry.manga.coverUrl"
            :alt="entry.manga.title"
            class="w-4 h-5 object-cover rounded-sm"
          />
          <span class="truncate max-w-28">{{ entry.manga.title }}</span>
        </button>

        <!-- No followed entries hint -->
        <p v-if="followedEntries.length === 0" class="text-sm text-base-content/40 italic">
          {{ t('notifications.noFollowed') }}
        </p>
      </div>

      <!-- Loading -->
      <div v-if="isPending" class="space-y-3">
        <div v-for="i in 6" :key="i" class="h-28 rounded-xl bg-base-200 animate-pulse" />
      </div>

      <!-- Empty state -->
      <div
        v-else-if="!articlePage?.items?.length"
        class="flex flex-col items-center justify-center py-24 gap-4 text-base-content/40"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-14 w-14 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 12h6m-6-4h4" />
        </svg>
        <p class="text-lg font-medium">{{ t('notifications.empty') }}</p>
      </div>

      <!-- Article list -->
      <div v-else class="space-y-3">
        <ArticleCard
          v-for="article in articlePage.items"
          :key="article.id"
          :article="article"
        />
      </div>

      <!-- Pagination -->
      <div v-if="(articlePage?.totalPages ?? 0) > 1" class="flex justify-center gap-2 mt-8">
        <button class="btn btn-sm btn-ghost" :disabled="page === 1" @click="page--">‹</button>
        <button
          v-for="p in articlePage!.totalPages"
          :key="p"
          class="btn btn-sm"
          :class="p === page ? 'btn-primary' : 'btn-ghost'"
          @click="page = p"
        >
          {{ p }}
        </button>
        <button class="btn btn-sm btn-ghost" :disabled="page === articlePage!.totalPages" @click="page++">›</button>
      </div>
    </div>
  </div>
</template>
```

---

### Step 26 — Add follow toggle to `MangaDetailPage.vue`

**File:** `front/src/pages/MangaDetailPage.vue` *(modify)*
**Why:** The user opts in/out of notifications from the manga detail view.

```typescript
// Add these imports:
import { toggleFollow } from '@/api/collection'
import { useMutation, useQueryClient } from '@tanstack/vue-query'

// Inside <script setup>:
const qc = useQueryClient()

const followMutation = useMutation({
  mutationFn: () => toggleFollow(props.id), // or however the current entry id is accessed
  onSuccess: (data) => {
    qc.invalidateQueries({ queryKey: ['collection'] })
    qc.invalidateQueries({ queryKey: ['collection', props.id] })
  },
})
```

Add this toggle button in the template, near the reading status controls:

```vue
<!-- Follow / unfollow toggle -->
<button
  class="btn btn-sm gap-2"
  :class="entry.notificationsEnabled ? 'btn-secondary' : 'btn-ghost border border-base-300'"
  :disabled="followMutation.isPending.value"
  @click="followMutation.mutate()"
>
  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
  </svg>
  {{ entry.notificationsEnabled ? t('notifications.following') : t('notifications.follow') }}
</button>
```

---

## i18n Keys

**`front/src/i18n/fr.json`** — replace the `notifications` block:

```json
"notifications": {
  "title": "Actualités",
  "subtitle": "Articles et actualités trouvés pour vos œuvres suivies",
  "empty": "Aucun article trouvé pour le moment",
  "filterBy": "Filtrer par",
  "allMangas": "Tous",
  "noFollowed": "Activez le suivi sur une série pour voir ses actualités",
  "follow": "Suivre",
  "following": "Suivi ✓",
  "markRead": "Marquer comme lu"
}
```

**`front/src/i18n/en.json`** — replace the `notifications` block:

```json
"notifications": {
  "title": "News Feed",
  "subtitle": "Articles and news found for your followed works",
  "empty": "No articles found yet",
  "filterBy": "Filter by",
  "allMangas": "All",
  "noFollowed": "Enable notifications on a series to see its news",
  "follow": "Follow",
  "following": "Following ✓",
  "markRead": "Mark as read"
}
```

---

## QA Gates

Run every gate in order. Do not skip any.

### 1. Install packages + rebuild containers

```bash
docker compose exec back composer require symfony/scheduler symfony/mailer
docker compose restart worker
```

### 2. Run migration

```bash
docker compose exec back php bin/console doctrine:migrations:diff
# Review the generated migration in back/migrations/
docker compose exec back php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec back php bin/console messenger:setup-transports
```

### 3. PHP Static Analysis (PHPStan)

```bash
docker compose exec back ./vendor/bin/phpstan analyse --memory-limit=512M
```
Expected: `[OK] No errors`

### 4. PHP Code Style (CS Fixer / PHPCS)

```bash
docker compose exec back ./vendor/bin/phpcs
# To auto-fix:
docker compose exec back ./vendor/bin/phpcbf
```

### 5. PHPUnit

```bash
docker compose exec back ./vendor/bin/phpunit
```

### 6. Migration status clean

```bash
docker compose exec back php bin/console doctrine:migrations:status
```
Expected: `Current Version` = `Latest Available Version`

### 7. Frontend Type Check

```bash
docker compose exec app npx tsc --noEmit
```

### 8. Frontend Lint

```bash
docker compose exec app npx eslint src --ext .ts,.vue
```

### 9. Smoke Test (manual)

```
1. docker compose up -d
2. Login at http://localhost:5173/gate
3. Navigate to a manga detail page → click "Suivre" → badge turns to "Suivi ✓"
4. Navigate to /notifications → verify filter shows the followed manga
5. Trigger cron manually:
   docker compose exec back php bin/console debug:scheduler  (verify task appears)
   docker compose exec worker php bin/console messenger:consume async --limit=1 -vv
6. Check Mailpit at http://localhost:8025 → verify notification email received
7. Check /api/articles/activity-logs → verify ActivityLog entries created
```

---

## Execution Checklist

### Backend
- [ ] Step 1  — CollectionEntry: add `notificationsEnabled` + `lastNotifiedAt`
- [ ] Step 2  — Article entity
- [ ] Step 3  — ActivityLog entity
- [ ] Step 4  — ArticleRepositoryInterface
- [ ] Step 5  — ActivityLogRepositoryInterface
- [ ] Step 6  — CollectionRepositoryInterface: add `findFollowed()`
- [ ] Step 7  — DoctrineArticleRepository
- [ ] Step 8  — DoctrineActivityLogRepository
- [ ] Step 9  — DoctrineCollectionRepository: implement `findFollowed()`
- [ ] Step 10 — ToggleFollowCommand + ToggleFollowHandler
- [ ] Step 11 — GetArticlesQuery + GetArticlesHandler
- [ ] Step 12 — GetActivityLogsQuery + GetActivityLogsHandler
- [ ] Step 13 — FetchRssFeedMessage + FetchRssFeedHandler
- [ ] Step 14 — FetchJikanNewsMessage + FetchJikanNewsHandler
- [ ] Step 15 — SendFollowingNotificationMessage + SendFollowingNotificationHandler
- [ ] Step 16 — DispatchFollowingCrawlTask (Symfony Scheduler)
- [ ] Step 17 — ArticleController (`/api/articles`)
- [ ] Step 18 — CollectionController: add `PATCH /{id}/follow`
- [ ] Step 19 — Email Twig template
- [ ] Step 20a — composer require symfony/scheduler symfony/mailer
- [ ] Step 20b — messenger.yaml: add routes + scheduler_default transport
- [ ] Step 20c — services.yaml: aliases + RSS feed config + email binding
- [ ] Step 20d — back/.env: MAILER_DSN + NOTIFICATION_EMAIL
- [ ] Step 20e — docker-compose.yml: worker consumes scheduler_default + mailer env

### Database
- [ ] Migration generated and reviewed
- [ ] Migration applied (`doctrine:migrations:migrate`)
- [ ] Messenger transports set up (`messenger:setup-transports`)

### Frontend
- [ ] Step 21 — types/index.ts: Article, ActivityLog, ArticlePage, updated CollectionEntry
- [ ] Step 22 — api/notification.ts: getArticles, getActivityLogs
- [ ] Step 23 — api/collection.ts: toggleFollow
- [ ] Step 24 — ArticleCard.vue molecule
- [ ] Step 25 — NotificationsPage.vue rewrite
- [ ] Step 26 — MangaDetailPage.vue: follow toggle
- [ ] i18n keys added to fr.json and en.json

### QA
- [ ] PHPStan passes
- [ ] PHPCS passes
- [ ] PHPUnit passes
- [ ] Doctrine migrations status clean
- [ ] TypeScript noEmit passes
- [ ] ESLint passes
- [ ] Smoke test done (follow toggle + manual cron trigger + email in Mailpit)

### Git
- [ ] All changes on feature branch
- [ ] Single commit (`git commit --amend` if needed)
- [ ] PR created
