# PHP / Symfony — Architecture & Coding Conventions

## Naming Conventions

### Suffixes (all types carry a descriptive suffix)

| Type | Suffix | Example |
|------|--------|---------|
| Command | `Command` | `PlaceOrderCommand` |
| Command handler | `Handler` | `PlaceOrderHandler` |
| Query | `Query` | `GetOrderQuery` |
| Query handler | `Handler` | `GetOrderHandler` |
| Domain Event | `Event` | `OrderPlacedEvent` |
| Domain Event handler | `Handler` | `OrderPlacedHandler` |
| Repository interface | `Interface` | `OrderRepositoryInterface` |
| Repository impl | descriptive prefix | `DoctrineOrderRepository` |
| Domain Service | `Service` | `PricingService` |
| Value Object | descriptive noun | `Email`, `OrderId`, `Money` |
| DTO / Read model | `View` or `Dto` | `OrderView`, `UserDto` |
| Exception | `Exception` | `OrderNotFoundException` |
| Enum | `Enum` | `OrderStatusEnum` |
| Middleware | `Middleware` | `ExceptionMiddleware` |
| Listener | `Listener` | `SendWelcomeEmailListener` |

### Prefixes

| Type | Prefix | Example |
|------|--------|---------|
| Abstract class | `Abstract` | `AbstractRepository`, `AbstractHandler` |

### Domain Event naming — past tense + `Event`

```php
// ✅ correct
final class OrderPlacedEvent implements DomainEvent { ... }
final class UserRegisteredEvent implements DomainEvent { ... }
final class PaymentFailedEvent implements DomainEvent { ... }

// ❌ wrong — no suffix, present tense
final class PlaceOrder { ... }
final class UserRegister { ... }
```

### Interface naming

```php
// ✅ correct
interface OrderRepositoryInterface { ... }
interface EventBusInterface { ... }
interface PasswordHasherInterface { ... }

// ❌ wrong
interface OrderRepository { ... }   // ambiguous with concrete class
interface IOrderRepository { ... }  // Hungarian notation — never
```

---

## Architecture — Hexagonal (Ports & Adapters)

```
src/
└── <BoundedContext>/
    ├── Domain/
    │   ├── Model/               # Entities, Aggregates, Value Objects
    │   ├── Event/               # Domain Events (past tense + Event suffix)
    │   ├── Repository/          # Repository interfaces (ports)
    │   ├── Service/             # Domain Services (pure business logic)
    │   └── Exception/           # Domain exceptions
    ├── Application/
    │   ├── Command/             # Commands + Handlers (write side)
    │   ├── Query/               # Queries + Handlers (read side)
    │   └── EventHandler/        # Domain Event handlers (async side effects)
    └── Infrastructure/
        ├── Input/
        │   ├── Http/            # Controllers
        │   ├── Console/         # Console commands
        │   └── Messenger/       # Async consumers
        └── Output/
            ├── Persistence/     # Doctrine repositories
            ├── Messaging/       # Event bus, queues
            └── ExternalService/ # HTTP clients, third-party APIs

tests/
├── Shared/InMemory/             # In-memory fakes of repository interfaces
├── Unit/<BoundedContext>/Domain/
├── Integration/<BoundedContext>/Application/
└── Functional/<BoundedContext>/
```

**Dependency rule (strict):**
- `Domain` → nothing (zero imports from Application or Infrastructure)
- `Application` → `Domain` only
- `Infrastructure` → `Application` + `Domain`

---

## Domain Events — use as much as possible

Domain Events are the primary mechanism for side effects. A handler does **one** thing;
everything else is triggered by an event.

**When to raise a Domain Event:**
- Any meaningful state change in an aggregate
- Anything that should trigger a side effect (send email, update read model, notify, audit)
- Any cross-bounded-context communication

**Pattern:**

```php
// ✅ Raise event in the Aggregate
final class Order
{
    private array $events = [];

    public static function place(CustomerId $customerId, array $items): self
    {
        $order = new self($customerId, $items);
        $order->events[] = new OrderPlacedEvent($order->id, new DateTimeImmutable());
        return $order;
    }

    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];
        return $events;
    }
}

// ✅ Handler does ONE thing — dispatch events after
#[AsMessageHandler]
final class PlaceOrderHandler
{
    public function __invoke(PlaceOrderCommand $command): void
    {
        $order = Order::place(
            new CustomerId($command->customerId),
            $command->items
        );
        $this->orders->save($order);
        $this->eventBus->dispatchAll($order->releaseEvents()); // triggers side effects
    }
}

// ✅ Side effect in a dedicated EventHandler
#[AsMessageHandler]
final class OrderPlacedHandler  // NOT OnOrderPlacedHandler
{
    public function __invoke(OrderPlacedEvent $event): void
    {
        $this->mailer->sendOrderConfirmation($event->orderId);
    }
}

// ❌ Wrong — handler does multiple things
#[AsMessageHandler]
final class PlaceOrderHandler
{
    public function __invoke(PlaceOrderCommand $command): void
    {
        $order = Order::place(...);
        $this->orders->save($order);
        $this->mailer->sendConfirmation($order->id); // ❌ side effect directly in handler
        $this->analytics->track($order->id);          // ❌ another side effect
    }
}
```

**Dispatch Domain Events AFTER persistence** — never before.

---

## CQRS — Commands and Queries

```php
// Command — mutates state, returns void
final readonly class PlaceOrderCommand
{
    public function __construct(
        public string $customerId,
        public array  $items,
    ) {}
}

// Query — reads state, returns a typed object or array
final readonly class GetOrderQuery
{
    public function __construct(public string $orderId) {}
}

// Query handler returns a typed DTO, never an entity
#[AsMessageHandler]
final class GetOrderHandler
{
    public function __invoke(GetOrderQuery $query): OrderView
    {
        return $this->readModel->findById($query->orderId)
            ?? throw new OrderNotFoundException($query->orderId);
    }
}
```

Response of query handler must always be a **typed object or array** — `toArray()` belongs
to the Domain model, not the handler.

---

## Controllers

```php
// ✅ Correct controller
final class PlaceOrderController extends AbstractController
{
    #[Route('/orders', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] PlaceOrderRequest $request): JsonResponse
    {
        $this->commandBus->dispatch(new PlaceOrderCommand(
            customerId: $request->customerId,
            items:      $request->items,
        ));
        return new JsonResponse(null, Response::HTTP_ACCEPTED);
    }
}

// ❌ Wrong — manual parsing, try/catch, logic in controller
final class PlaceOrderController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);  // ❌
        if (empty($data['customerId'])) { ... }             // ❌ validation here
        try {
            $this->commandBus->dispatch(...);
        } catch (DomainException $e) {                      // ❌ no try/catch
            return new JsonResponse(['error' => ...], 400);
        }
    }
}
```

**Rules:**
- Always `#[MapRequestPayload]` to get a typed request object
- No logic — dispatch only
- No `try/catch` — a middleware catches every exception and returns the right response
- Every exception must be logged in the middleware

---

## Code Style

```php
// ✅ final everywhere possible
final class PlaceOrderHandler { ... }

// ✅ readonly class when all properties are readonly
final readonly class PlaceOrderCommand
{
    public function __construct(
        public string $customerId,
        public array  $items,
    ) {}
}

// ❌ never readonly on individual properties of a readonly class
final class PlaceOrderCommand
{
    public function __construct(
        public readonly string $customerId,  // ❌ redundant if class is readonly
    ) {}
}

// ✅ public readonly instead of getter
final class Order
{
    public readonly OrderId $id;  // ✅
}

// ❌ no getter methods
final class Order
{
    private OrderId $id;
    public function getId(): OrderId { return $this->id; }  // ❌ useless wrapper
}

// ✅ PHP 8.4 — no parentheses on new for chaining
$result = new OrderId($id)->value();  // ✅
$result = (new OrderId($id))->value(); // ❌

// ✅ always use imports
use App\Domain\Model\Order;           // ✅
$order = new \App\Domain\Model\Order; // ❌ FQCN inline

// ✅ explicit types everywhere — no mixed, no missing return types
public function handle(PlaceOrderCommand $command): void { ... }  // ✅
public function handle($command) { ... }                          // ❌
```

**Strict rules:**
- All code in English — zero French (code, comments, variable names, strings)
- No `mixed` types anywhere
- Every `@phpstan-ignore` must have a comment explaining why
- PHPStan level 10, zero errors
- Separate exception class per error — never reuse `DomainException` directly
- Shared abstract exceptions (`AbstractNotFoundException`) live in `Shared/Domain/Exception/`

---

## Testing

### Structure
```
tests/
├── Shared/InMemory/          # Fakes implementing Domain interfaces
│   └── InMemoryOrderRepository.php  (implements OrderRepositoryInterface)
├── Unit/<BoundedContext>/Domain/    # Entities, VOs, Domain Services
├── Integration/<BoundedContext>/Application/  # Handlers via KernelTestCase
└── Functional/<BoundedContext>/    # HTTP endpoints via WebTestCase
```

### Rules
- **Every feature needs tests** — at minimum one happy path + one error path
- Unit tests use in-memory fakes — never hit the real database
- Functional tests use `WebTestCase` + DAMA auto transaction rollback
- Authenticated endpoints need `getAuthToken()` helper in `AuthenticatedWebTestCase`
- `php bin/phpunit` must pass before any PR

### In-memory fake pattern
```php
final class InMemoryOrderRepository implements OrderRepositoryInterface
{
    /** @var array<string, Order> */
    private array $orders = [];

    public function save(Order $order): void
    {
        $this->orders[(string) $order->id] = $order;
    }

    public function findById(OrderId $id): ?Order
    {
        return $this->orders[(string) $id] ?? null;
    }
}
```
