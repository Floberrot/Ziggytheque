---
name: railway-devsecops
description: >
  DevSecOps skill for containerizing projects with Docker and deploying to Railway.
  Trigger this skill whenever the user mentions Docker, Railway, containerization,
  Dockerfile, docker-compose, railway.toml, `railway up`, deploying to Railway,
  or wants to make their project "run on Railway". Also trigger when the user asks
  to "dockerize" their project, set up a container, or prepare for cloud deployment —
  even if they don't say "Railway" or "Docker" explicitly. Use proactively when you
  can see a project has no Dockerfile or railway.toml but the user is talking about
  deployment.
---

# Railway DevSecOps Skill

You are acting as a DevSecOps engineer. Your job is to make the user's project run
securely on Docker and Railway, following security best practices.

---

## Workflow

Work through these steps in order, adapting to what already exists in the project.

### Step 1 — Detect the project

Scan the project root to understand:
- **Language / runtime**: check for `package.json`, `requirements.txt`, `Pipfile`,
  `pyproject.toml`, `composer.json`, `go.mod`, `Cargo.toml`, `Gemfile`, `pom.xml`,
  `build.gradle`, etc.
- **Framework**: e.g. Express, FastAPI, Django, Laravel, Rails, Spring Boot, Axum.
- **Existing Docker artifacts**: `Dockerfile`, `.dockerignore`, `docker-compose.yml`.
- **Existing Railway config**: `railway.toml`, `railway.json`, `railway.yaml`.
- **Port**: look for explicit PORT usage in code or config; Railway injects `$PORT`
  at runtime — the app must bind to it.
- **Build step**: compiled language / bundler? Multi-stage build is needed.

Read the relevant files (package.json, requirements.txt, etc.) to understand
dependencies and scripts before writing any files.

### Step 2 — Security audit

Before generating anything, scan for security issues and report findings:

| Check | What to look for |
|-------|-----------------|
| Hardcoded secrets | `SECRET`, `PASSWORD`, `TOKEN`, `API_KEY`, `DATABASE_URL` literals in source files |
| `.env` in repo | `.env*` files committed (not in `.gitignore`) |
| Existing Dockerfile issues | Running as root, `latest` tags, `ADD` with remote URLs, secrets in `ENV` or `ARG` |
| Exposed ports | Only expose what the app actually needs |
| Sensitive files in image | `.git/`, `node_modules/`, `__pycache__/`, test fixtures with real credentials |

Report each finding as **[CRITICAL]**, **[WARNING]**, or **[INFO]**, with a brief
remediation hint. Do this even if the issue is in existing files — fixing it is part
of the job.

### Step 3 — Generate or update the Dockerfile

Use the language-specific reference in `references/` for the right base image and
pattern. Apply these rules universally:

**Security rules (non-negotiable):**
- Pin the base image to a specific minor version tag — never `latest`
- Use a minimal base image: `alpine`, `slim`, or `distroless` variants
- Create and use a non-root user (`RUN addgroup ... && adduser ...`)
- Never place secrets, passwords, or tokens in `ENV` or `ARG` instructions
- Use `COPY --chown=<user>:<group>` instead of post-copy `chown` in `RUN`
- Combine `RUN` instructions with `&&` to minimize layers
- Add `HEALTHCHECK` if the app exposes HTTP

**Railway rules:**
- The app must listen on `$PORT` (Railway sets this; do not hardcode a port)
- Do not include `railway.toml` commands that duplicate `CMD` / `ENTRYPOINT`

**Multi-stage builds:**  
Use multi-stage whenever there's a build step (compiled binaries, bundled assets,
transpilation). The builder stage can be heavier; the final stage should be minimal.

If a Dockerfile already exists, update it in place — preserve any custom logic,
just fix security issues and add what's missing.

### Step 4 — Generate or update .dockerignore

Create `.dockerignore` if missing; add entries if it exists but is incomplete.

Always include:
```
.git
.gitignore
.env
.env.*
*.env
node_modules/
__pycache__/
*.pyc
.pytest_cache/
.DS_Store
Thumbs.db
*.log
coverage/
.nyc_output/
dist/         # only if built inside Docker via multi-stage
build/        # same
*.md
.dockerignore
docker-compose*.yml
railway.toml
```

Adapt for the project's language (e.g., add `vendor/` for PHP/Go, `target/` for
Rust/Java, `.venv/` for Python).

### Step 5 — Generate or update Railway config

Railway accepts either `railway.toml` (TOML) or `railway.json` (JSON). Both are
equivalent. Use whichever the project already has; default to `railway.json` for new
projects because it includes a `$schema` for IDE autocompletion.

**railway.json** (preferred for new projects):
```json
{
  "$schema": "https://railway.com/railway.schema.json",
  "build": {
    "builder": "DOCKERFILE",
    "dockerfilePath": "Dockerfile"
  },
  "deploy": {
    "healthcheckPath": "/",
    "healthcheckTimeout": 100,
    "restartPolicyType": "ON_FAILURE",
    "restartPolicyMaxRetries": 10
  }
}
```

**railway.toml** (alternative):
```toml
[build]
builder = "DOCKERFILE"
dockerfilePath = "Dockerfile"

[deploy]
healthcheckPath = "/"
healthcheckTimeout = 100
restartPolicyType = "ON_FAILURE"
restartPolicyMaxRetries = 10
```

If the app has no HTTP health endpoint, omit `healthcheckPath`. Mention this.

If a config file already exists, merge settings — don't clobber custom entries.

**Monorepo / subdirectory projects**: if the backend lives in `back/` and the
frontend in `front/`, set `dockerfilePath` to the correct relative path (e.g.
`"Dockerfile"` at root level that references `back/` and `front/` as build contexts
is the cleanest approach — avoid per-subdirectory Railway configs unless the user
has separate Railway services for frontend and backend).

### Step 6 — Scaffold docker-compose.yml (always, no need to ask)

Always scaffold `docker-compose.yml` with the **standard 5-container stack**. Do not ask —
every project starts with these 5 services. Read `references/php.md` for the monorepo layout
and `references/docker-gotchas.md` section 9 for the canonical `docker-compose.yml` template.

Standard services (non-negotiable):
| Service | Image | Purpose |
|---------|-------|---------|
| `back` | `Dockerfile.dev` (FrankenPHP + PHP 8.4) | Symfony 8 API |
| `app` | `node:22-alpine` | Vue 3 + Vite dev server |
| `db` | `postgres:17-alpine` | PostgreSQL database |
| `mailer` | `axllent/mailpit:latest` | Email catcher (web UI: 8025, SMTP: 1025) |
| `worker` | `Dockerfile.dev` | Symfony Messenger consumer + `/messenger` dashboard |

Also always create `Dockerfile.dev` — a thin dev image that extends FrankenPHP with PHP
extensions and Composer. See `references/docker-gotchas.md` section 3 for the correct pattern.

### Step 7 — Report and next steps

After all files are written, produce two output files:

**SECURITY_REPORT.md** — detailed findings only (see Step 2 format).

**SUMMARY.md** — the actionable wrap-up. Always include this, structured as:

```markdown
# Railway DevSecOps Summary — <Project Name>

## Files created / updated
- Dockerfile            ✓ (describe: multi-stage, non-root, pinned base image used)
- .dockerignore         ✓
- railway.toml          ✓
- docker/nginx.conf     ✓  (if applicable)
- docker/supervisord.conf ✓  (if applicable)

## Security findings
- [CRITICAL] ...
- [WARNING]  ...
- [INFO]     ...
(or: "No security findings." if clean)

## Next steps
1. **Set secrets in Railway dashboard** — never commit these:
   - APP_KEY / APP_SECRET, DATABASE_URL, REDIS_URL, API tokens, etc.
2. Add `.env` to `.gitignore` if not already.
3. Install Railway CLI:
   \`\`\`bash
   npm i -g @railway/cli
   \`\`\`
4. Login and link:
   \`\`\`bash
   railway login
   railway link
   \`\`\`
5. Deploy:
   \`\`\`bash
   railway up
   \`\`\`
6. Open app:
   \`\`\`bash
   railway open
   \`\`\`
```

If credentials were committed or baked into an image, add a **"Rotate credentials"**
step at the top of the Next steps list — this is the most urgent action.

---

## Language references

For language-specific base images, multi-stage patterns, and build tooling, read the
appropriate file from `references/`:

- **Node.js / TypeScript / Vue 3** → `references/node.md`
  - Covers: Vue 3 SPA (Vite), Express, Fastify, NestJS, Bun
- **PHP / Symfony 8** → `references/php.md`
  - Covers: Symfony 8 + FrankenPHP + PHP 8.4 (the only supported stack)
  - Also covers: monorepo (`back/` + `front/`), Symfony Messenger worker on Railway
- **Python / FastAPI / Django / Flask** → `references/python.md`
  - Covers: uvicorn, gunicorn, uv/poetry, multi-stage with native deps

If the language isn't listed, use an official slim or alpine image, apply the
universal security rules from Step 3, and note any assumptions in the summary.
