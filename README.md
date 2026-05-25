# 📚 ZiggyTheque

> A sophisticated manga collection management system with real-time notifications, price tracking, and wishlist management. Built with modern full-stack technologies for optimal performance and maintainability.

[![GitHub](https://img.shields.io/badge/GitHub-floberrot%2FZiggyTheque-blue?logo=github)](https://github.com/floberrot/ZiggyTheque)

**Production:** [https://www.ziggytheque.fr](https://www.ziggytheque.fr) — hosted on [Railway](https://railway.app).
The apex `ziggytheque.fr` redirects (301) to `www.ziggytheque.fr` via an OVH redirection.

---

## ✨ Features

- **Manga Library Management** – Search, add, and organize your manga collection with volumes
- **Collection Tracking** – Mark volumes as owned or read, track your reading progress
- **Wishlist** – Create wishlist items and convert them to collection entries when purchased
- **Price Tracking** – Per-volume price recording feeds collection value analytics
- **Advanced Stats** – View collection analytics: total owned, read count, wishlist size, collection value, genre breakdown
- **Notifications** – Real-time notifications for collection updates and events
- **Dark Mode** – Elegant dark theme by default with light mode support
- **Multi-language** – French (default) and English support via i18n
- **Google Books Integration** – Search and import manga from Google Books API

---

## 🛠️ Tech Stack

### Backend
- **Framework:** Symfony 8 + PHP 8.4
- **Server:** FrankenPHP (modern PHP application server)
- **Database:** PostgreSQL 17
- **Architecture:** Hexagonal + CQRS (Symfony Messenger)
- **Quality:** PHPStan, PHPCS, Deptrac, PHPUnit
- **Task Queue:** Symfony Messenger worker

### Frontend
- **Framework:** Vue 3 + TypeScript
- **Build Tool:** Vite (lightning-fast)
- **Styling:** Tailwind CSS + DaisyUI
- **State Management:** Pinia
- **Data Fetching:** TanStack Vue Query
- **Routing:** Vue Router 4
- **i18n:** Vue-i18n

### Infrastructure
- **Local:** 5 Docker containers (back, app, db, mailer, worker) orchestrated by `docker-compose.yml`
- **Production:** [Railway](https://railway.app) — 4 services (backend FrankenPHP, Messenger worker, frontend nginx SPA, PostgreSQL)
- **Email Provider (prod):** [Resend](https://resend.com) — `symfony/resend-mailer` transport, domain `ziggytheque.fr` verified (DKIM + MX)
- **Quality Gates:** Pre-commit hooks, commitlint

---

## 🚀 Quick Start

### Prerequisites
- Docker & Docker Compose
- Make

### Setup (First Time)

```bash
# Clone the repository
git clone <repository-url>
cd ZiggyTheque

# One-command setup: starts containers, generates keys, runs migrations
make setup
```

The `setup` command will:
1. Start all 5 Docker containers
2. Install backend & frontend dependencies
3. Generate JWT keypair
4. Run database migrations
5. Reset & restart the worker

### Development

```bash
# Start all containers (sync vendor locally)
make dev

# View logs
make logs

# Backend shell
make shell-back

# Frontend shell
make shell-front
```

### Access Points

`make dev` prints these URLs at the end of startup so you can ⌘-click them straight from the terminal.

| Service | Local (dev) | Production |
|---------|-------------|------------|
| Frontend | http://localhost:5173 | https://www.ziggytheque.fr |
| Backend API | http://localhost:8000 | served behind the frontend via internal Railway URL (proxied through nginx `/api`) |
| Database | localhost:5432 | Railway-managed PostgreSQL 17 (internal hostname) |
| Mailer | http://localhost:8025 (Mailpit) | [Resend](https://resend.com) — see [`docs/resend.md`](docs/resend.md) |
| Messenger | http://localhost:8000/messenger | `/messenger` on the backend service (HTTP Basic, `MONITOR_USER` / `MONITOR_PASSWORD`) |

---

## 📋 Project Structure

```
ZiggyTheque/
├── back/                    # Symfony backend
│   └── src/
│       ├── Shared/          # CQRS buses, exception handling
│       ├── Auth/            # Gate authentication
│       ├── Manga/           # Manga & Volume entities (price lives on Volume)
│       ├── Collection/      # User collection tracking
│       ├── Wishlist/        # Wishlist management
│       ├── Stats/           # Analytics queries
│       └── Notification/    # Notification system (Discord + email follows)
├── front/                   # Vue 3 frontend
│   └── src/
│       ├── components/      # Atomic design (atoms, molecules, organisms)
│       ├── pages/           # Route pages
│       ├── stores/          # Pinia stores
│       ├── api/             # API client layer
│       └── i18n/            # Translations (fr, en)
└── docker-compose.yml       # 5 containers configuration
```

---

## 🔐 Authentication

**Gate-based access** – No user accounts, single shared password:

```bash
# POST /api/auth/gate
{
  "password": "your_gate_password"
}
```

Returns a JWT token. All `/api/*` routes require:
```
Authorization: Bearer <jwt_token>
```

**Setup:**
```bash
# In back/.env or docker-compose.yml
GATE_PASSWORD=your_secure_password
JWT_PASSPHRASE=your_jwt_passphrase
```

---

## 📡 API Endpoints

### Authentication
- `POST /api/auth/gate` – Authenticate with gate password

### Manga
- `GET /api/manga?q=<query>` – Search your library
- `GET /api/manga/external?q=<query>` – Search Google Books
- `GET /api/manga/:id` – Get manga details
- `POST /api/manga` – Add new manga
- `POST /api/manga/:id/volumes` – Add volumes

### Collection
- `GET/POST /api/collection` – List/create collection entries
- `PATCH /api/collection/:id/status` – Update entry status
- `PATCH /api/collection/:id/volumes/:volId/toggle` – Toggle owned/read
- `DELETE /api/collection/:id` – Remove from collection

### Wishlist
- `GET/POST /api/wishlist` – List/create wishlist items
- `POST /api/wishlist/:id/purchase` – Move to collection
- `DELETE /api/wishlist/:id` – Remove from wishlist

### Stats
- `GET /api/stats` – Collection analytics (totalOwned, totalRead, totalWishlist, collectionValue, genreBreakdown)

### Notifications
- `GET /api/notifications` – List notifications
- `PATCH /api/notifications/:id/read` – Mark as read

---

## 🏗️ Architecture

### Design Patterns

**Hexagonal Architecture** – Clean separation of concerns:
- **Domain Layer** – Business logic, entities, interfaces
- **Application Layer** – Commands, Queries, Event handlers (CQRS)
- **Infrastructure Layer** – Controllers, repositories, external API clients

**CQRS** via Symfony Messenger:
```php
// Command pattern (state-changing)
$this->commandBus->dispatch(new AddMangaCommand(...));

// Query pattern (read-only)
$stats = $this->queryBus->dispatch(new GetStatsQuery());

// Domain events (side effects)
$this->eventBus->dispatch(new MangaAddedEvent(...));
```

**Exception Handling** – No try/catch in controllers, all exceptions routed through ExceptionListener
**Bounded Contexts** – Each feature is isolated (Auth, Manga, Collection, etc.)
**Type Safety** – `final readonly` classes, strict typing throughout

### Quality Assurance

```bash
# Run all quality gates
make qa

# PHP quality (style, static analysis, tests)
make php-qa

# Frontend quality (linting, type check, tests)
make vue-qa

# Architecture boundaries (Deptrac)
make deptrac
```

---

## 🔧 Configuration

### Environment Variables

Create `back/.env.local` for local dev overrides:

```env
GATE_PASSWORD=your_secure_password
APP_SECRET=32_character_random_string
DATABASE_URL=postgresql://user:pass@db:5432/ziggytheque?serverVersion=17
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your_jwt_passphrase
MONITOR_USER=monitor
MONITOR_PASSWORD=your_monitor_password
GOOGLE_BOOKS_API_KEY=your_api_key  # Optional, for Google Books integration

# Mailer — local dev uses Mailpit (default). For Resend in local "staging" mode, see docs/resend.md
MAILER_DSN=smtp://mailer:1025
NOTIFICATION_EMAIL=you@example.com
```

### Production (Railway)

Set the following on the **back** and **worker** services (both need the same DB + mailer config):

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<openssl rand -hex 32>
DATABASE_URL=postgresql://<user>:<pass>@<railway-pg-host>:5432/<db>
JWT_PASSPHRASE=<strong random passphrase>
GATE_PASSWORD=<gate password>
GOOGLE_BOOKS_API_KEY=<key>
CORS_ALLOW_ORIGIN=^https://(www\.)?ziggytheque\.fr$
MONITOR_USER=monitor
MONITOR_PASSWORD=<strong password>

# Resend (see docs/resend.md)
MAILER_DSN=resend+api://<RESEND_API_KEY>@default
NOTIFICATION_EMAIL=notifications@ziggytheque.fr
```

On the **frontend** Railway service:

```env
BACKEND_URL=https://<backend-service>.up.railway.app
```

The frontend nginx proxies `/api` and `/proxy` to `BACKEND_URL`, so the SPA stays same-origin under `www.ziggytheque.fr` — no CORS hit in normal usage.

---

## 📚 Common Tasks

```bash
# Database
make migrate              # Run migrations
make migration            # Generate new migration
make fixtures             # Load sample data

# Code Quality
make phpstan              # Static analysis
make phpcs                # Code style check
make phpcbf               # Auto-fix code style
make lint                 # Lint & fix Vue
make type-check           # TypeScript check
make test-php             # Run PHP tests
make test-vue             # Run Vue tests

# Development
make cc                   # Clear cache
make jwt-keys             # Regenerate JWT keypair
make logs-back            # Tail backend logs
make logs-worker          # Tail worker logs
```

---

## 🐳 Docker Services

| Service | Container | Purpose |
|---------|-----------|---------|
| **back** | PHP 8.4 + FrankenPHP | Symfony API (port 8000) |
| **app** | Node.js | Vite dev server (port 5173) |
| **db** | PostgreSQL 17 | Data persistence (port 5432) |
| **mailer** | Mailpit | Email catcher for local dev (port 8025) — production uses Resend, see [`docs/resend.md`](docs/resend.md) |
| **worker** | PHP 8.4 | Symfony Messenger consumer |

---

## 🚨 Important Notes

### FrankenPHP & HTTPS
FrankenPHP auto-enables HTTPS in Docker by default. This project disables it:
```yaml
# docker-compose.yml
SERVER_NAME: "http://:80"  # Prevent auto-HTTPS
```

### Vite Proxy
Inside Docker, use service names, not localhost:
```yaml
# docker-compose.yml
BACKEND_URL: http://back:80
```

### Migrations
**Always run migrations after first boot:**
```bash
make setup  # Handled automatically
# Or manually:
make migrate
```

---

## 📖 Documentation

- **Backend Architecture:** See [`.claude/backend.md`](.claude/backend.md) for detailed rules and patterns
- **Project Instructions:** See [`.claude/CLAUDE.md`](.claude/CLAUDE.md) for code style, git discipline, and core patterns
- **Email Setup (Resend):** See [`docs/resend.md`](docs/resend.md) for production email configuration
- **Real-time (Mercure):** See [`docs/mercure.md`](docs/mercure.md) for the SSE / server-push wiring
- **Production Deployment:** See [`PLAN_deploy-production.md`](PLAN_deploy-production.md) for the Railway deployment plan
- **Make Help:** `make help` – View all available commands

---

## 📝 License

Private project. For questions, contact the repository owner.

---

<div align="center">

**Made with ❤️ by [Florian Berrot](https://github.com/floberrot)**

</div>
