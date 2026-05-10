# Mercure — Real-time updates from server to browser

This document explains how Mercure is wired in Ziggytheque, how messages flow end-to-end, and what to change when deploying outside local Docker (Railway, staging, prod).

> Use case in this project: the **async auto-covers** batch — `POST /api/manga/{id}/auto-covers` returns `202 Accepted` immediately, the worker resolves volume covers one by one, and the SPA listens to Mercure for live progress (`batch_started`, `volume_resolved`, `volume_failed`, `batch_completed`).

---

## 1. What Mercure is, in one paragraph

Mercure is a protocol over **HTTP/2 Server-Sent Events (SSE)** for pushing updates from server to browser. A single long-lived connection (`EventSource` in JS) stays open and the server publishes JSON payloads on **topics**. Browsers subscribe to one or more topics and receive every update as a `MessageEvent`. There is no WebSocket, no polling, no driver, no separate process to run — in our setup the **hub is embedded inside FrankenPHP / Caddy** and runs on the same port as the HTTP API.

---

## 2. Architecture in this project

```
                                                    ┌─────────────────────────────────┐
   POST /api/manga/{id}/auto-covers                 │   FrankenPHP container (back)    │
┌──────────┐ ──────────────────────────────────────►│ ┌─────────────────────────────┐ │
│  SPA     │                                        │ │ Symfony controller           │ │
│  Vue     │ ◄── 202 { batchId, mercureUrl, JWT } ──┤ │   dispatch(Sync command)     │ │
│ (:5173)  │                                        │ │   ↳ generate batchId         │ │
└────┬─────┘                                        │ │   ↳ enqueue async message    │ │
     │                                              │ │   ↳ mint subscriber JWT      │ │
     │                                              │ └─────────────────────────────┘ │
     │                                              │ ┌─────────────────────────────┐ │
     │                                              │ │ Mercure hub (Caddy module)   │ │
     │  EventSource                                 │ │  /.well-known/mercure         │ │
     │  GET mercureUrl?topic=…&authorization=JWT    │ │  • verifies subscriber JWT   │ │
     │ ◄────────────────────────────────────────────┤ │  • streams SSE to subscribers│ │
     │     batch_started / volume_resolved / …      │ │                              │ │
     │                                              │ └────────────────▲────────────┘ │
     │                                              └──────────────────┼──────────────┘
     │                                                                 │ POST /.well-known/mercure
     │                                                                 │ Authorization: Bearer <publisher JWT>
     │                                              ┌──────────────────┴──────────────┐
     │                                              │   Worker container               │
     │                                              │ ┌─────────────────────────────┐ │
     │                                              │ │ messenger:consume async       │ │
     │                                              │ │   ↳ AutoCoversBatchHandler    │ │
     │                                              │ │   ↳ resolve covers one by one │ │
     │                                              │ │   ↳ publish to Mercure hub    │ │
     │                                              │ └─────────────────────────────┘ │
     │                                              └─────────────────────────────────┘
     ▼
 Toast: "Récupération des couvertures…" → "Tome 5/12 traité" → "10/12 couvertures ajoutées"
```

Three concurrent pieces of plumbing:

| Piece | Where it runs | Reads / writes |
|---|---|---|
| **HTTP API** | `back` container (FrankenPHP) | reads request, writes 202 response |
| **Mercure hub** | `back` container (Caddy `mercure` directive) | accepts publishes from Symfony, streams SSE to browsers |
| **Async worker** | `worker` container (`messenger:consume async`) | reads from Doctrine queue, publishes to Mercure hub via HTTP |

The SPA (Vite, port 5173) talks to:
- The HTTP API via `/api` (proxied by Vite to `http://back:80` inside Docker, `localhost:8000` outside).
- The Mercure hub directly via the URL returned in the 202 response (`MERCURE_PUBLIC_URL`).

---

## 3. Message flow, end-to-end

1. **User clicks "Compléter les couvertures"** in `MangaDetailPage.vue`.
2. SPA calls `POST /api/manga/{id}/auto-covers` with the JWT bearer.
3. `MangaController::autoCovers` dispatches a sync `StartCoverBatchCommand`. Its handler:
   - Verifies the manga exists (else `404`).
   - Generates a UUIDv4 `batchId`.
   - Enqueues `AutoCoversBatchMessage` on the **async** Messenger transport (Doctrine queue).
   - Mints a short-lived **subscriber JWT** scoped to the topic `https://ziggytheque.app/cover-batch/{batchId}` (TTL 600s).
   - Returns `{ batchId, mercureUrl, subscriberToken, topic }`.
4. Symfony responds **`202 Accepted`** with that payload. The HTTP request is over.
5. The SPA opens an `EventSource` on `mercureUrl?topic=<topic>&authorization=<subscriberToken>` and starts a progressive toast.
6. The **worker** picks up the message from the queue. The handler:
   - Loads the manga.
   - Computes `total` = volumes that will be processed.
   - Publishes `batch_started`.
   - Iterates volumes via `CoverBatchResolver`. For each volume: try to resolve a cover (MangaDex → OpenLibrary → Google Books), then publish `volume_resolved` or `volume_failed`.
   - Saves the manga.
   - Publishes `batch_completed`.
7. The Mercure hub fans out each publish to every connected subscriber on that topic — in practice, just the one SPA tab. The SPA updates the toast in place.
8. On `batch_completed`, the SPA closes the `EventSource` and refreshes the collection query.

### Topic and security model

- **One UUID topic per batch.** A user clicks twice → two batches, two topics, two JWTs. No cross-leak between users or tabs.
- The hub is configured with **`subscriber_jwt` only** (no `anonymous`): a request without a valid JWT is rejected with `401`.
- The subscriber JWT has a single claim `mercure.subscribe = ["https://ziggytheque.app/cover-batch/<batchId>"]` and a 10-minute TTL. It cannot be reused for another batch.
- The publisher JWT is server-side only (in `back` and `worker` env), never sent to the browser.

### Why query-string auth, not header?

`EventSource` (the only browser API for SSE) does not allow custom headers. The Mercure spec defines a fallback: append `?authorization=<jwt>` and the hub parses it. The token never lands in a `Referer` because SSE requests don't carry one, but it **does** land in access logs — which is acceptable for a 10-minute scope-locked token.

---

## 4. Files and configuration

### Backend (`back/`)

| File | Purpose |
|---|---|
| `Caddyfile` | Declares the `mercure` directive on the same Caddy site as `php_server`. |
| `config/packages/mercure.yaml` | Symfony bundle config — registers the `default` hub used by `HubInterface`. |
| `config/services.yaml` | Aliases the Domain ports to their Mercure adapters (`CoverBatchProgressPublisherInterface`, `CoverBatchSubscriberAuthorizerInterface`). |
| `config/packages/messenger.yaml` | Routes `AutoCoversBatchMessage` to the `async` transport. |
| `src/Manga/Domain/CoverBatchProgressPublisherInterface.php` | Domain port — what the handler injects. |
| `src/Manga/Infrastructure/Mercure/MercureCoverBatchProgressPublisher.php` | Adapter — calls `HubInterface::publish()`. |
| `src/Manga/Infrastructure/Mercure/MercureCoverBatchSubscriberAuthorizer.php` | Mints HS256 JWTs scoped to a single batch topic. |

### Frontend (`front/`)

| File | Purpose |
|---|---|
| `src/api/manga.ts` | `autoFillCovers` returns `{ batchId, mercureUrl, subscriberToken, topic }`. |
| `src/composables/useCoverBatchProgress.ts` | Wraps `EventSource`, parses payloads, exposes reactive `progress`. |
| `src/stores/useUiStore.ts` | `addProgressToast` / `updateProgressToast` / `closeProgressToast`. |
| `src/pages/MangaDetailPage.vue` | Wires the composable to the toast. |

---

## 5. Environment variables

These are the **only** Mercure-related env vars in the project. Set the same values on both the `back` and `worker` containers — the worker also publishes to the hub.

| Variable | What it is | Local dev value | Production value |
|---|---|---|---|
| `MERCURE_PUBLISHER_JWT_KEY` | Secret used by the **hub** to verify publisher JWTs and by Symfony to sign them. **Must be the same on both sides.** | `!ChangeThisLocalMercureSecret!` | random 32+ byte secret, rotated per environment |
| `MERCURE_SUBSCRIBER_JWT_KEY` | Secret used by the **hub** to verify subscriber JWTs and by `MercureCoverBatchSubscriberAuthorizer` to sign them. **Must be the same on both sides.** Different from the publisher key. | `!ChangeThisLocalMercureSubSecret!` | random 32+ byte secret, distinct from the publisher key |
| `MERCURE_JWT_SECRET` | Secret used by `symfony/mercure-bundle` to sign publisher tokens automatically. Same value as `MERCURE_PUBLISHER_JWT_KEY`. | `!ChangeThisLocalMercureSecret!` | same value as `MERCURE_PUBLISHER_JWT_KEY` |
| `MERCURE_URL` | Internal hub URL — used by Symfony when **publishing** updates. | `http://back:80/.well-known/mercure` (Docker service-name) | `http://localhost:80/.well-known/mercure` (same container in Railway), or `https://api.example.com/.well-known/mercure` if the hub lives on another host |
| `MERCURE_PUBLIC_URL` | Public hub URL — sent to the browser in the 202 response. The SPA opens its `EventSource` on this URL. | `http://localhost:8000/.well-known/mercure` | `https://api.example.com/.well-known/mercure` |

**Why two URLs?** Inside Docker, the back container reaches the hub at `http://back:80` (service name). The browser cannot resolve `back` — it needs the public hostname. The two values are usually identical in production (same machine, public hostname) but differ in local dev.

### Generating production secrets

```bash
# Generate a 32-byte hex secret per key
openssl rand -hex 32

# Use a different value for each of:
#   MERCURE_PUBLISHER_JWT_KEY  (also reused as MERCURE_JWT_SECRET)
#   MERCURE_SUBSCRIBER_JWT_KEY
```

Never commit these to the repo. In Railway, set them under **Variables** on both the `back` and `worker` services.

---

## 6. The Caddyfile directive

```caddy
:{$PORT:80} {
    root * /app/public

    encode zstd br gzip

    mercure {
        publisher_jwt {env.MERCURE_PUBLISHER_JWT_KEY} HS256
        subscriber_jwt {env.MERCURE_SUBSCRIBER_JWT_KEY} HS256
        cors_origins http://localhost:5173 http://localhost:8000
    }

    php_server
}
```

What each line does:

- `publisher_jwt … HS256` — only requests carrying a JWT signed with this key may **POST** to the hub (i.e. publish updates). Symfony does this transparently.
- `subscriber_jwt … HS256` — only requests carrying a JWT signed with this key may **GET** the SSE stream. The SPA passes the token via `?authorization=`.
- `cors_origins` — origins allowed to open `EventSource` connections. **Must include the SPA origin in production.**

### Production Caddyfile changes

Replace the local CORS origins with your real frontend origin:

```caddy
mercure {
    publisher_jwt {env.MERCURE_PUBLISHER_JWT_KEY} HS256
    subscriber_jwt {env.MERCURE_SUBSCRIBER_JWT_KEY} HS256
    cors_origins https://app.example.com
}
```

If the frontend lives on multiple hosts (preview deployments, alternate domains), list all of them space-separated.

> **Do not** add `anonymous` in production — it disables subscriber auth and exposes every topic to the public.

---

## 7. Production checklist

When promoting to a new environment (Railway, staging, prod), go through this list:

- [ ] Rotate `MERCURE_PUBLISHER_JWT_KEY` to a fresh 32-byte secret.
- [ ] Rotate `MERCURE_SUBSCRIBER_JWT_KEY` to a fresh 32-byte secret (different from the publisher key).
- [ ] Set `MERCURE_JWT_SECRET` to the **same value** as `MERCURE_PUBLISHER_JWT_KEY`.
- [ ] Set `MERCURE_URL` to the in-network hub URL (most likely `http://localhost:80/.well-known/mercure` if back+hub run in the same container).
- [ ] Set `MERCURE_PUBLIC_URL` to the **public HTTPS** URL of the hub (e.g. `https://api.example.com/.well-known/mercure`).
- [ ] Update `back/Caddyfile` `cors_origins` to the production SPA origin (or set it via env if you template the file).
- [ ] Verify the `worker` service has the **same** five Mercure variables — the worker publishes too.
- [ ] Confirm the public URL terminates TLS (HTTPS). Browsers refuse SSE over plain HTTP from an HTTPS page.
- [ ] Confirm any reverse proxy or CDN in front of the hub does **not** buffer responses (no `proxy_buffering` for `/.well-known/mercure`). Buffering breaks SSE.
- [ ] Confirm read timeouts on the proxy are at least 60s (preferably 600s) — SSE connections are long-lived.
- [ ] Smoke test: `curl -N -H "Authorization: Bearer <subscriber-jwt>" "<public-url>?topic=test"` should hang open and stream.

---

## 8. Operating notes

### Logs

- **Publisher errors** (Symfony failed to reach the hub) appear in the `back` and `worker` logs as `Symfony\Component\Mercure\Exception\…`.
- **Subscriber errors** (browser kicked, JWT expired) appear in the Caddy access log on the `back` container with status `401`.

### Debugging end-to-end

1. Verify the hub answers: `curl -i http://localhost:8000/.well-known/mercure?topic=foo` → expect `401 Unauthorized` (subscriber JWT missing).
2. Verify the API responds: `curl -X POST -H "Authorization: Bearer $JWT" http://localhost:8000/api/manga/<id>/auto-covers -d '{}' -H 'Content-Type: application/json'` → expect `202` and a JSON body.
3. Subscribe with the returned token: `curl -N "$mercureUrl?topic=$topic&authorization=$subscriberToken"` → keeps the connection open and prints SSE frames as the worker runs.
4. Tail the worker: `docker compose logs -f worker` → look for `App\Manga\Application\AutoCovers\AutoCoversBatchHandler` lines.

### Known limits

- **One topic per batch** — fine for this use case. If you ever need cross-batch broadcast (e.g. "any cover work happening for manga X"), publish to two topics from the handler — Mercure supports multi-topic publishes natively.
- **JWT TTL is 10 minutes.** Batches longer than that will see the SSE connection drop with `401`. If batches grow that long, either bump the TTL or have the SPA re-mint a token when it sees an `error` event.
- **No replay.** A subscriber that connects after `batch_completed` sees nothing. The SPA must connect *before* the worker publishes. In practice the dispatch → consume window is < 1s, so the SPA opens the connection right after the 202 and before the worker starts.

### When *not* to use Mercure

- For request/response data: use the regular API.
- For data that must be persisted server-side: persist first, publish to Mercure as a notification.
- For high-volume per-user streams (every keystroke): SSE is one connection per topic per browser tab — fine for dozens of concurrent batches, not for thousands of per-keystroke events.

---

## 9. References

- Mercure protocol: https://mercure.rocks/spec
- FrankenPHP Mercure module: https://frankenphp.dev/docs/mercure/
- Symfony Mercure component: https://symfony.com/doc/current/mercure.html
- The async batch implementation plan: `.agents/plans/2026-05-10-async-auto-covers-mercure.md`
