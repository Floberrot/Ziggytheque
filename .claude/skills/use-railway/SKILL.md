---
name: use-railway
description: >
  Operate Railway infrastructure for Ziggytheque: deploy services, manage
  environment variables, troubleshoot failures, configure domains, and release to
  production. Use this skill whenever the user mentions Railway, deployments,
  production, services, environments, build failures, or infrastructure, even if
  they don't say "Railway" explicitly. Always enforces the project's exact
  service topology, Dockerfile rules, FrankenPHP configuration, and CI/CD flow.
allowed-tools: Bash(railway:*), Bash(which:*), Bash(command:*), Bash(npm:*), Bash(npx:*), Bash(curl:*)
---

# Use Railway — Ziggytheque Production

## CRITICAL PROJECT RULES (read before every action)

These are non-negotiable. Any deviation will crash the production deployment.

### Rule 1 — FrankenPHP SERVER_NAME
`SERVER_NAME` **must** be `"http://:80"` on every service that runs FrankenPHP.
- `http://:80` → correct (HTTP only, no TLS redirect)
- `:80` → wrong (Caddy auto-enables HTTPS → 308 redirect loop)
- omitted → wrong (same result)

### Rule 2 — Deploy from repo root for back and worker
`railway up` for `ziggytheque-back` and `ziggytheque-worker` **must** be executed from the repository root.
The root `railway.json` and root `Dockerfile` are the build artifacts.
Running from `back/` triggers Nixpacks (no Dockerfile there) and produces a broken image.

```bash
# CORRECT — run from repo root
railway up --service ziggytheque-back --ci -m "..."

# WRONG — never do this
cd back && railway up --service ziggytheque-back --ci
```

### Rule 3 — Frontend deploy from front/ directory
`railway up` for `ziggytheque-front` **must** be executed from `front/`.
The `front/railway.json` and `front/Dockerfile` are used.

```bash
cd front && railway up --service ziggytheque-front --ci -m "..."
```

### Rule 4 — Frontend BACKEND_URL is mandatory
The `ziggytheque-front` service **must** have `BACKEND_URL` set to the internal
Railway URL of the backend (e.g. `https://ziggytheque-back.railway.internal` or
the Railway private URL). If unset, `envsubst` leaves a literal `${BACKEND_URL}`
in the nginx config and nginx refuses to start.

### Rule 5 — Worker start command
`ziggytheque-worker` uses the **same root Dockerfile** as the backend (target: `prod`)
but has a custom `startCommand` in `worker/railway.json`:
```
php bin/console messenger:consume async --time-limit=3600 -vv
```
Never change the image source. Never point it at `front/Dockerfile`.

### Rule 6 — Nginx resolver is 8.8.8.8 (not 127.0.0.11)
`front/nginx.conf.template` uses `resolver 8.8.8.8 8.8.4.4;`.
`127.0.0.11` is Docker Compose only and is not available on Railway.
Never change the resolver to a Docker-internal address.

### Rule 7 — Never bypass CI gates
Production deploys go through `deploy-production.yml`. All 5 quality gates must
pass before the approval environment is presented. Never push directly via
`railway up` without the full CI flow for production.

---

## Service Topology

| Railway Service | Dockerfile | Deploy From | Start Command |
|---|---|---|---|
| `ziggytheque-back` | root `Dockerfile` (target: `prod`) | repo root | FrankenPHP (default CMD) |
| `ziggytheque-worker` | root `Dockerfile` (target: `prod`) | repo root | `php bin/console messenger:consume async --time-limit=3600 -vv` |
| `ziggytheque-front` | `front/Dockerfile` (target: `prod`) | `front/` | nginx (default CMD) |

## Railway Configuration Files

### Root `railway.json` (back service)
```json
{
  "build": {
    "builder": "DOCKERFILE",
    "dockerfilePath": "Dockerfile"
  },
  "deploy": {
    "restartPolicyType": "ON_FAILURE",
    "restartPolicyMaxRetries": 3
  }
}
```

### `worker/railway.json`
```json
{
  "build": {
    "builder": "DOCKERFILE",
    "dockerfilePath": "../Dockerfile",
    "dockerBuildTarget": "prod",
    "buildArgs": {
      "JWT_PASSPHRASE": "${{JWT_PASSPHRASE}}"
    }
  },
  "deploy": {
    "startCommand": "php bin/console messenger:consume async --time-limit=3600 -vv",
    "restartPolicyType": "ON_FAILURE",
    "restartPolicyMaxRetries": 5
  }
}
```

### `front/railway.json`
```json
{
  "build": {
    "builder": "DOCKERFILE",
    "dockerfilePath": "Dockerfile",
    "buildArgs": {
      "VITE_API_BASE_URL": "${{VITE_API_BASE_URL}}"
    }
  },
  "deploy": {
    "restartPolicyType": "ON_FAILURE",
    "restartPolicyMaxRetries": 3
  }
}
```

---

## Dockerfile Architecture

### Root `Dockerfile` (3 stages)

```
Stage 1 — frontend (node:22-alpine)
  └─ Builds Vue SPA → /app/dist/

Stage 2 — base (dunglas/frankenphp:1-php8.4)
  └─ Installs: pdo_pgsql, intl, zip, opcache, apcu

Stage 3 — prod (from base)
  ├─ ENV APP_ENV=prod APP_DEBUG=0 SERVER_NAME="http://:80"
  ├─ COPY back/ .
  ├─ composer install --no-dev --optimize-autoloader --classmap-authoritative
  ├─ COPY --from=frontend /app/dist /app/public/spa
  ├─ COPY back/Caddyfile /etc/caddy/Caddyfile
  ├─ COPY back/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
  ├─ EXPOSE 80
  └─ ENTRYPOINT ["docker-entrypoint.sh"]
     CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
```

### `front/Dockerfile` (2 stages)

```
Stage 1 — builder (node:22-alpine)
  ├─ ARG VITE_API_BASE_URL (intentionally empty — uses relative /api calls)
  └─ npm run build → /app/dist/

Stage 2 — prod (nginx:alpine)
  ├─ COPY /app/dist → /usr/share/nginx/html
  ├─ COPY nginx.conf.template → /etc/nginx/templates/default.conf.template
  ├─ ENV BACKEND_URL=http://BACKEND_URL_NOT_SET  ← must be overridden in Railway
  └─ EXPOSE 80
```

---

## Caddyfile (`back/Caddyfile`)

```caddyfile
{
  frankenphp
  order php_server before file_server
}

{$SERVER_NAME:http://:80} {
  root * /app/public
  encode zstd br gzip
  php_server
}
```

`{$SERVER_NAME:http://:80}` reads the `SERVER_NAME` env var, defaults to `http://:80`.
This is why `SERVER_NAME=http://:80` is required — without `http://` prefix, Caddy
enables TLS and Railway's HTTP proxy gets a redirect loop.

---

## Nginx Config (`front/nginx.conf.template`)

```nginx
server {
    listen 80;
    server_name _;
    root /usr/share/nginx/html;
    index index.html;

    resolver 8.8.8.8 8.8.4.4 valid=30s ipv6=off;  # NOT 127.0.0.11 (Docker-only)

    location ~ ^/(api|proxy)/ {
        set $backend ${BACKEND_URL};    # envsubst at container start
        proxy_pass $backend;
        proxy_ssl_server_name on;       # sends correct SNI for HTTPS backends
        proxy_ssl_verify     off;       # inter-service, no cert validation needed
        proxy_set_header Host              $proxy_host;
        proxy_set_header X-Real-IP         $remote_addr;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location / {
        try_files $uri $uri/ /index.html;  # SPA fallback
    }
}
```

---

## Entrypoint Sequence (`back/docker-entrypoint.sh`)

Runs on every container start for both `ziggytheque-back` and `ziggytheque-worker`:

1. Generate JWT keypair (`lexik:jwt:generate-keypair --overwrite`)
2. Warm Symfony cache (`cache:warmup --env=prod`)
3. Test raw DB connection (with SSL/no-SSL diagnostics, checks `DATABASE_URL` and `DATABASE_PUBLIC_URL`)
4. Run migrations (`doctrine:migrations:migrate --no-interaction --env=prod`)
5. `exec "$@"` → start FrankenPHP (or the worker start command)

**If migrations fail**, the container exits. Check logs:
```bash
railway logs --service ziggytheque-back --lines 100
```

---

## Required Environment Variables

### `ziggytheque-back` and `ziggytheque-worker`

| Variable | Value / Notes |
|---|---|
| `SERVER_NAME` | `http://:80` — **mandatory, exact value** |
| `APP_ENV` | `prod` |
| `APP_DEBUG` | `0` |
| `APP_SECRET` | 32-char random string |
| `DATABASE_URL` | `postgresql://user:pass@host:5432/dbname?serverVersion=17&charset=utf8` |
| `DATABASE_PUBLIC_URL` | Optional public proxy URL (Railway provides both) |
| `JWT_PASSPHRASE` | Passphrase for JWT keypair generation |
| `GATE_PASSWORD` | Single password for the app gate |
| `MONITOR_USER` | HTTP Basic user for `/messenger` |
| `MONITOR_PASSWORD` | HTTP Basic password for `/messenger` |
| `CORS_ALLOW_ORIGIN` | Regex of allowed origins (e.g. `^https://.*\.railway\.app$`) |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default` |

### `ziggytheque-front`

| Variable | Value / Notes |
|---|---|
| `BACKEND_URL` | Internal Railway URL of the back service (e.g. `https://ziggytheque-back.up.railway.app`) — **mandatory** |
| `VITE_API_BASE_URL` | Leave empty — frontend uses relative `/api` calls |

---

## CI/CD Flow (`deploy-production.yml`)

Triggered by: push to `main`

```
Parallel quality gates (must all pass):
  ├─ phpcs          (PHP_CodeSniffer, from back/)
  ├─ deptrac        (architecture enforcement, from back/)
  ├─ phpstan        (static analysis, needs PostgreSQL service)
  ├─ db-migrations  (migration + schema validate, needs PostgreSQL service)
  ├─ tests          (PHPUnit, needs PostgreSQL service)
  └─ frontend       (vue-tsc + ESLint, from front/)
         │
         ▼
  approve           (GitHub "production" environment — manual approval required)
         │
    ┌────┴────────────────┐
    ▼                     ▼
  deploy-back           deploy-front
  (from repo root)      (from front/)
         │
         ▼
  deploy-worker
  (from repo root, needs deploy-back)
         │
         ▼
  approve-release   (GitHub "release" environment — manual approval)
         │
         ▼
  release           (semantic version tag + GitHub Release via release.yml)
```

### GitHub Secrets Required

| Secret | Used By |
|---|---|
| `RAILWAY_TOKEN` | All deploy jobs |
| `RAILWAY_PROJECT_ID` | All deploy jobs |
| `RAILWAY_PRODUCTION_ENVIRONMENT_ID` | All deploy jobs |
| `JWT_PASSPHRASE` | phpstan, db-migrations, tests jobs |

---

## Manual Deploy (emergency / hotfix)

Only when CI is not available. Always check context first.

```bash
command -v railway || npm install -g @railway/cli
railway whoami --json

# Deploy backend (from repo root)
railway up --service ziggytheque-back --ci \
  -m "hotfix: <description>"

# Deploy worker (from repo root, after back is healthy)
railway up --service ziggytheque-worker --ci \
  -m "hotfix: <description>"

# Deploy frontend (from front/ directory)
cd front && railway up --service ziggytheque-front --ci \
  -m "hotfix: <description>"
```

---

## Troubleshooting Playbook

### Container exits immediately after deploy

```bash
railway logs --service ziggytheque-back --lines 200
```

Check for:
- `[diag] private+no-ssl FAILED` → `DATABASE_URL` wrong or DB not reachable
- `Migration failed` → run migrations manually (see below)
- `JWT keypair generation failed` → `JWT_PASSPHRASE` not set
- `Symfony cache warmup failed` → `APP_SECRET` not set, or code error

### Database migration failure

```bash
# Check migration status
railway run --service ziggytheque-back \
  php bin/console doctrine:migrations:status

# Run migrations manually
railway run --service ziggytheque-back \
  php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

### Frontend shows blank page or API 502

1. Check `BACKEND_URL` is set on `ziggytheque-front`:
   ```bash
   railway variable list --service ziggytheque-front --json
   ```
2. Check backend is healthy:
   ```bash
   railway service status --service ziggytheque-back --json
   ```
3. Check nginx is proxying correctly:
   ```bash
   railway logs --service ziggytheque-front --lines 50
   ```

### 308 redirect loop on backend

`SERVER_NAME` is wrong. Set it to exactly `http://:80`:
```bash
railway variable set SERVER_NAME="http://:80" --service ziggytheque-back
railway variable set SERVER_NAME="http://:80" --service ziggytheque-worker
```
Then redeploy both services.

### Worker not consuming messages

```bash
railway logs --service ziggytheque-worker --lines 100
# Should show: [OK] Consuming messages from transport "async"
```

If it's running FrankenPHP instead of the consumer, `startCommand` in
`worker/railway.json` was overridden. Restore:
```
startCommand: php bin/console messenger:consume async --time-limit=3600 -vv
```

### Build uses Nixpacks instead of Dockerfile

Railway is picking up the wrong build context. Verify:
- `railway.json` exists in the deploy root
- `railway.json` has `"builder": "DOCKERFILE"` and correct `dockerfilePath`
- You are running `railway up` from the correct directory

---

## Railway Resource Model

- **Workspace** — billing and team scope
- **Project** — collection of services (`ziggytheque-back`, `ziggytheque-worker`, `ziggytheque-front`, PostgreSQL DB)
- **Environment** — isolated config plane (`production`)
- **Service** — single deployable unit
- **Deployment** — point-in-time release with build/runtime logs

## CLI Quick Reference

```bash
railway status --json                                      # current context
railway whoami --json                                      # auth + workspace
railway service status --all --json                        # all services health
railway variable list --service <svc> --json               # list variables
railway variable set KEY=value --service <svc>             # set a variable
railway logs --service <svc> --lines 200                   # recent logs
railway run --service <svc> <command>                      # run one-off command
```

## Preflight Before Any Deploy

```bash
command -v railway                # CLI installed
railway whoami --json             # authenticated
railway --version                 # check version (upgrade if needed: railway upgrade)
railway status --json             # linked project context
```

## Parsing Railway URLs

```
https://railway.com/project/<PROJECT_ID>/service/<SERVICE_ID>?environmentId=<ENV_ID>
```

Extract IDs and pass via `--project`, `--environment`, `--service` flags instead
of running `railway link` (avoids global state changes).

## Execution Rules

1. Prefer Railway CLI; fall back to GraphQL API only for operations the CLI doesn't expose.
2. Use `--json` output for reliable parsing.
3. Resolve context (project + environment + service) before any mutation.
4. For destructive actions (delete service, drop database), confirm intent first.
5. After mutations, verify with a read-back command.
