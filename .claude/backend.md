# Backend Architecture Rules

These rules apply to every module, every handler, every new class — no exceptions.

---

## R1 — Domain Events are the universal mechanism for ALL side effects

No handler ever directly calls a logger, a notifier, a mailer, or any cross-cutting service.
Every side effect — ActivityLog lifecycle, notifications, emails, audit — is triggered by
dispatching a **semantic domain event**. Listeners (in Infrastructure) react independently.

This applies to ActivityLog, Discord, email, metrics, and **anything else** — not just notifications.

```php
// ❌ Bad — handler directly manages ActivityLog AND calls infra services
$log = new ActivityLog(...);
$this->activityLogRepository->save($log);
// ... do work ...
$log->markSuccess($count);
$this->activityLogRepository->save($log);
$this->discord->sendNewArticles(...);

// ✅ Good — handler dispatches semantic events; listeners handle everything
$this->eventBus->dispatch(new RssFetchStartedEvent($feedName, $feedUrl, $entry->id));
// ... do work ...
$this->eventBus->dispatch(new RssFetchSucceededEvent($feedName, $entry->id, $newCount));
// if error:
$this->eventBus->dispatch(new RssFetchFailedEvent($feedName, $entry->id, $e->getMessage()));
```

Events are defined in `App\{Module}\Shared\Event\` so any module can dispatch or listen.
Listeners live in `Infrastructure\` (ActivityLog listener, Discord listener, etc.).

```
App\Notification\Shared\Event\RssFetchStartedEvent    ← dispatched by handler
App\Notification\Shared\Event\RssFetchSucceededEvent  ← dispatched by handler
App\Notification\Shared\Event\RssFetchFailedEvent     ← dispatched by handler

App\Notification\Infrastructure\Listener\ActivityLogRssFetchListener   ← creates/updates log
App\Notification\Infrastructure\Listener\DiscordNewArticlesListener    ← sends Discord embed
```

**Every module follows this pattern** — not just Notification.
`UserRegistered`, `OrderShipped`, `CollectionEntryAdded` are all domain events that
trigger their own listeners for logging, notifications, etc.

---

## R2 — Each module exposes a `Shared/` SDK — zero business logic inside

Every module that needs to be called by other modules exposes an `App\{Module}\Shared\` namespace.
`Shared` contains **only interfaces and DTOs** — it delegates to Domain/Application/Infrastructure
but **never implements business logic itself**.

```php
// App\Collection\Shared\CollectionReaderInterface.php
interface CollectionReaderInterface
{
    public function findById(string $id): ?CollectionEntryDto;
}

// App\Collection\Infrastructure\Doctrine\DoctrineCollectionReader.php
final readonly class DoctrineCollectionReader implements CollectionReaderInterface { ... }
```

```yaml
# services.yaml
App\Collection\Shared\CollectionReaderInterface:
    alias: App\Collection\Infrastructure\Doctrine\DoctrineCollectionReader
```

Other modules import `CollectionReaderInterface`, **never** `CollectionEntry` (the Domain entity).

---

## R3 — Handlers contain zero business logic

A handler is a **pure orchestrator**. It contains no `if`, no computation, no conditions,
no data transformation. Every piece of logic lives in a Domain service or a Domain Event listener.

A handler does exactly three things:
1. Load aggregates via domain interfaces (repository, shared SDK)
2. Call a Domain service / Domain method that encapsulates the logic
3. Dispatch domain events — listeners handle ALL side effects

The handler never knows about ActivityLog, Discord, email, or any infrastructure.
It dispatches **semantic events** describing what happened in the domain.

```php
// ❌ Bad — handler contains logic, conditions, infrastructure calls
final readonly class FetchRssFeedHandler
{
    public function __invoke(FetchRssFeedMessage $message): void
    {
        $log = new ActivityLog(...);
        $this->activityLogRepository->save($log);
        $response = $this->httpClient->request(...);
        $xml = simplexml_load_string($response->getContent());  // logic in handler
        foreach ($xml->channel->item as $item) {                // logic in handler
            if (str_contains($item->title, $message->mangaTitle)) { // logic in handler
                // ...
            }
        }
        $log->markSuccess($count);
        $this->discord->sendNewArticles(...);
    }
}

// ✅ Good — handler is a pure orchestrator, zero logic
final readonly class FetchRssFeedHandler
{
    public function __invoke(FetchRssFeedMessage $message): void
    {
        $entry = $this->collectionRepository->findById($message->collectionEntryId);

        $this->eventBus->dispatch(new RssFetchStartedEvent(
            feedName: $message->feedName,
            feedUrl:  $message->feedUrl,
            entryId:  $entry->id,
        ));

        try {
            // All logic lives in the Domain service
            $newCount = $this->rssFeedParser->parse($message->feedUrl, $entry);

            $this->eventBus->dispatch(new RssFetchSucceededEvent(
                feedName: $message->feedName,
                entryId:  $entry->id,
                newCount: $newCount,
            ));
        } catch (Throwable $e) {
            $this->eventBus->dispatch(new RssFetchFailedEvent(
                feedName: $message->feedName,
                entryId:  $entry->id,
                error:    $e->getMessage(),
            ));
            throw $e;
        }
    }
}

// The logic lives here — Domain service, fully testable in isolation
final readonly class RssFeedParser
{
    public function parse(string $feedUrl, CollectionEntry $entry): int
    {
        // fetch, parse XML, match keywords, save articles — all logic here
    }
}
```
```

---

## R4 — Application handlers inject Domain interfaces; Infrastructure implements them

A handler in `Application/` **never imports** a class from `Infrastructure/`.
It injects a Domain interface. Infrastructure provides the concrete adapter.

```php
// App\Notification\Domain\NotificationSenderInterface.php  ← Domain (port)
interface NotificationSenderInterface
{
    public function sendNewArticles(string $mangaTitle, int $count, array $articles): void;
}

// App\Notification\Infrastructure\Discord\DiscordNotifier.php  ← Infrastructure (adapter)
final readonly class DiscordNotifier implements NotificationSenderInterface
{
    public function sendNewArticles(string $mangaTitle, int $count, array $articles): void
    {
        // HTTP call to Discord webhook
    }
}

// App\Notification\Application\SendNotificationHandler.php  ← Application
final readonly class SendNotificationHandler
{
    public function __construct(
        private NotificationSenderInterface $sender, // ← injects interface, not DiscordNotifier
    ) {}
}
```

```yaml
# services.yaml — wire the adapter
App\Notification\Domain\NotificationSenderInterface:
    alias: App\Notification\Infrastructure\Discord\DiscordNotifier
```

---

## R5 — One event, many independent listeners — never chain handlers

A domain event is broadcast once. Each Infrastructure listener handles exactly one side effect.
Adding a new side effect = adding a new listener, zero changes to the handler or existing listeners.

```
RssFetchSucceededEvent
  └─ ActivityLogSuccessListener   → marks log as success
  └─ DiscordNewArticlesListener   → sends Discord embed if newCount > 0
  └─ MailSummaryListener          → sends email digest (future)
  └─ MetricsListener              → increments counter (future)
```

```php
// ❌ Bad — adding email requires touching the handler
$this->discord->sendNewArticles(...);
$this->mailer->sendSummary(...); // added later, now handler is fat again

// ✅ Good — adding email = new listener, handler unchanged
// MailSummaryListener.php
#[AsEventListener]
final readonly class MailSummaryListener
{
    public function __invoke(RssFetchSucceededEvent $event): void
    {
        if ($event->newCount === 0) return;
        $this->mailer->sendSummary($event->entryId);
    }
}
```

---

## R6 — Deptrac inter-module dependencies via `{Module}_Shared` only

Modules **never** import another module's `Domain` or `Application` classes directly.
They use the module's `Shared` layer (the SDK).

```
// ❌ Bad — Notification imports Collection Domain entity directly
use App\Collection\Domain\CollectionEntry;

// ✅ Good — Notification imports Collection Shared interface
use App\Collection\Shared\CollectionReaderInterface;
```

Deptrac ruleset pattern:
```yaml
Notification_Application:
  - Notification_Domain
  - Collection_Shared   # ← only Shared is allowed, never Collection_Domain
  - Shared
```

Every module that needs to be consumed by others **must** expose a `Shared/` layer.
If a module has no `Shared/`, it cannot be depended upon by other modules.

---

## R8 — Always use PHP enums in DTOs and Request classes — never `string` + `#[Assert\Choice]`

Any field whose valid values form a closed set **must** use a typed PHP enum, not a raw `string`.
This applies to every layer: `Request` DTOs, application `Query`/`Command` objects, domain
methods, and entity properties.

```php
// ❌ Bad — recreates the enum in the wrong layer with validation boilerplate
#[Assert\Choice(choices: ['not_started', 'in_progress', 'completed', 'on_hold', 'dropped'])]
public string $status,

// ✅ Good — the type system enforces valid values; no Assert needed
public ReadingStatusEnum $status,
```

**With `#[MapQueryString]` / `#[MapRequestPayload]`:** Symfony's mapper calls
`TheEnum::from($value)` automatically. An invalid string from the client returns 422 with
no extra code. Drop `#[Assert\Choice]` entirely for enum-backed fields.

**No enum exists yet?** Create one in the relevant `Domain/` folder before writing the DTO.
If valid values belong to a specific bounded context, the enum lives in that context's `Domain/`.

```php
// New enum — back/src/Collection/Domain/CollectionSortEnum.php
enum CollectionSortEnum: string
{
    case RatingAsc  = 'rating_asc';
    case RatingDesc = 'rating_desc';
}
```

**Finding existing violations:**

```bash
grep -rn "Assert\\\\Choice" back/src/*/Infrastructure/Http/
```

Each hit where the choices map to a domain concept is a violation to fix.

---

## R9 — `#[MapQueryString]` on every controller that reads query parameters (GET, DELETE)

Never read from `Request $request` manually for query string parameters.
Use Symfony's `#[MapQueryString]` attribute (available since Symfony 6.3) with a dedicated
input DTO that lives in `Infrastructure/Http/`. Symfony maps, coerces, and validates the
parameters automatically; a constraint violation returns 422 with no controller boilerplate.

The request DTO:
- Is named with a **`Request` suffix** (e.g. `CollectionFilterRequest`, `DeleteMangaRequest`) so it is immediately recognisable as a controller-layer HTTP input object.
- Is **not** `final readonly` — Symfony mutates it after construction.
- Has **public** properties with defaults matching the "no filter / no input" state.
- Carries `#[Assert\*]` constraints for enum-like fields and numeric ranges.
- Lives in the same `Infrastructure/Http/` directory as the controller.

```php
// Infrastructure/Http/CollectionFilterRequest.php  ← request DTO, HTTP layer only
use Symfony\Component\Validator\Constraints as Assert;

final class CollectionFilterRequest
{
    public ?string $genre = null;

    #[Assert\Choice(choices: ['rating_asc', 'rating_desc'])]
    public ?string $sort = null;

    #[Assert\Positive]
    public int $page = 1;
}

// Infrastructure/Http/CollectionController.php  ← controller, no Request injection
use Symfony\Component\HttpKernel\Attribute\MapQueryString;

#[Route('/api/collection', methods: ['GET'])]
public function list(#[MapQueryString] CollectionFilterRequest $request): JsonResponse
{
    $query = new GetCollectionQuery(genre: $request->genre, sort: $request->sort, page: $request->page);
    return new JsonResponse(($this->queryBus)($query));
}
```

**Pairing with `#[MapRequestPayload]`:** use `#[MapRequestPayload]` for POST/PATCH/PUT (request
body), and `#[MapQueryString]` for GET/DELETE (query string). Never mix the two or fall back
to `$request->query->get(...)`.

---

## R7 — ActivityLog lifecycle is handled by 3 generic listeners, not N per-event listeners

**Never** create a listener file per domain event for ActivityLog. Use the 3 generic listeners in
`Notification/Infrastructure/Listener/`:

- `ActivityLogStartedEventListener` — handles ALL `StartedEventInterface` events → creates log
- `ActivityLogSucceededEventListener` — handles ALL `SucceededEventInterface` events → markSuccess
- `ActivityLogFailedEventListener` — handles ALL `FailedEventInterface` events → markError

These work because `SymfonyEventBus` dispatches each event to both its class name AND all its
`App\` interfaces. New events are automatically covered by implementing the correct marker interface.

```php
// ✅ Good — new event, zero listener boilerplate
final readonly class SomeNewStartedEvent implements StartedEventInterface
{
    // correlationId, sourceName, collectionEntryId (optional) as public properties
}

// Automatically handled by ActivityLogStartedEventListener — no new file needed
```

```php
// ❌ Bad — do not create ActivityLogSomeNewStartedListener.php
#[AsEventListener]
final readonly class ActivityLogSomeNewStartedListener { ... }
```

`ActivityLogEventHandler` (in `Notification/Domain/Service/`) centralises all creation/update logic.
The 3 generic listeners inject it and delegate — zero duplication.
