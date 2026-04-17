---
name: project-quality-setup
description: >
  Global quality, architecture, and coding conventions skill for PHP/Symfony/TypeScript/Vue projects.
  ALWAYS trigger this skill — on every project, on every coding task, even if not asked.
  Use it to: set up QA tooling from scratch (PHPStan, PHPCS/phpcbf, Deptrac, PHPUnit, ESLint,
  Prettier, vue-tsc, Vitest), enforce hexagonal architecture + DDD + CQRS, apply naming
  conventions, and ensure code quality gates pass before any PR. Trigger on any mention of
  "set up project", "init", "new project", "add QA", "quality", "conventions", or whenever
  writing PHP, Symfony, Vue, or TypeScript code in any project.
---

# Project Quality Setup & Conventions

## Non-negotiable project defaults

Every project uses:
- **Backend**: Symfony 8 + PHP 8.4 + FrankenPHP
- **Frontend**: Vue 3 + Vite + DaisyUI (with theme switch — always)
- **Docker stack**: 5 containers — `back`, `app`, `db`, `mailer` (Mailpit), `worker` (Messenger)
- **Queue dashboard**: `zenstruck/messenger-monitor-bundle` at `/messenger` (Horizon equivalent)

These are not configurable per-project. Always scaffold them.

This skill does two things:
1. **Setup mode** — installs and configures all QA tools when invoked explicitly
2. **Convention mode** — enforces architecture and coding rules on every piece of code written

Always read both `references/php-conventions.md` and `references/vue-conventions.md` before
writing any code. Read `references/qa-configs.md` when setting up or checking tool configs.
Read `references/docker-gotchas.md` before writing any `Dockerfile` or `docker-compose.yml`
(section 9 has the canonical 5-container template; section 10 has the Messenger dashboard setup).

---

## Setup Mode — run when invoked on a project

### Step 1 — Detect project type

Check for:
- `composer.json` → PHP project; check `symfony/framework-bundle` for Symfony
- `package.json` → JS/TS project; check for `vue`, `react`, `vite`, `nuxt`
- Both → full-stack monorepo (e.g. `back/` + `front/`)

Read existing configs: `phpstan.neon`, `phpcs.xml`, `deptrac.yaml`, `phpunit.xml.dist`,
`eslint.config.*`, `.prettierrc`, `vitest.config.*`, `Makefile`, `docker-compose.yml`.

### Step 2 — PHP QA setup (if PHP detected)

**Install missing dev dependencies:**
```bash
composer require --dev \
  phpstan/phpstan \
  phpstan/extension-installer \
  phpstan/phpstan-symfony \
  phpstan/phpstan-doctrine \
  squizlabs/php_codesniffer \
  deptrac/deptrac \
  phpunit/phpunit \
  dama/doctrine-test-bundle
```

**Generate missing config files** from `references/qa-configs.md`:
- `phpstan.neon` — level 10, Symfony extension
- `phpcs.xml` — PSR-12, auto-fix with phpcbf
- `deptrac.yaml` — hexagonal layer enforcement
- `phpunit.xml.dist` — 3 suites: Unit, Integration, Functional

**Add scripts to `composer.json`** if missing:
```json
"scripts": {
  "qa": ["@phpcbf", "@phpcs", "@phpstan", "@deptrac", "@test"],
  "phpcs":   "phpcs",
  "phpcbf":  "phpcbf",
  "phpstan": "phpstan analyse",
  "deptrac": "deptrac analyse",
  "test":    "php bin/phpunit"
}
```

**Create test directory structure** if missing:
```
tests/
├── Shared/
│   └── InMemory/        # In-memory repository fakes
├── Unit/
├── Integration/
└── Functional/
```

### Step 3 — JS/TS QA setup (if JS/TS detected)

**Install missing dev dependencies:**
```bash
# QA tooling
npm install --save-dev \
  eslint \
  @eslint/js \
  typescript-eslint \
  eslint-plugin-vue \
  eslint-config-prettier \
  prettier \
  vitest \
  @vitejs/plugin-vue \
  vue-tsc \
  jsdom \
  @vue/test-utils

# UI — DaisyUI + Tailwind (always for Vue 3 projects)
npm install -D tailwindcss @tailwindcss/vite daisyui@latest
```

**DaisyUI setup** (always for Vue 3):
- Add `@tailwindcss/vite` plugin to `vite.config.ts`
- Add `@import "tailwindcss"; @plugin "daisyui";` to `src/assets/main.css`
- Create `src/stores/useThemeStore.ts` and `src/components/atoms/BaseThemeSwitch.vue`
- See `references/vue-conventions.md` for full DaisyUI + theme switch implementation

**Generate missing config files** from `references/qa-configs.md`:
- `eslint.config.js` — flat config with TS + Vue + Prettier
- `.prettierrc` — standard formatting rules
- `vitest.config.ts` — jsdom environment, globals, `@` alias

**Add scripts to `package.json`** if missing:
```json
"scripts": {
  "qa":       "npm run lint && npm run type-check && npm run test",
  "lint":     "eslint src --fix",
  "lint:check": "eslint src",
  "format":   "prettier --write src",
  "type-check": "vue-tsc --noEmit",
  "test":     "vitest run",
  "test:watch": "vitest"
}
```

### Step 4 — Generate Makefile (always, every project)

**Always create a `Makefile` at the project root.** Read `references/makefile-templates.md`
and pick the right template:

- Full-stack monorepo (back/ + front/ + docker-compose.yml) → full-stack template
- PHP only → PHP-only template
- JS/TS only → JS/TS-only template

Adapt the template to the actual project:
- Replace service names, ports, and DB credentials to match the project's `docker-compose.yml`
- Add project-specific targets (e.g. `seed`, `fixtures`)
- Remove targets that don't apply (e.g. no `jwt-keys` if no JWT)
- Keep `.DEFAULT_GOAL := help` and the `help` awk target — always

The `Makefile` is the **single entry point** for every developer action.

### Step 5 — Report

After setup, print a summary:
```
## Quality Setup Complete

### PHP
- phpstan.neon     ✓ level 10
- phpcs.xml        ✓ PSR-12 (auto-fix: composer phpcbf)
- deptrac.yaml     ✓ hexagonal layers enforced
- phpunit.xml.dist ✓ Unit / Integration / Functional suites
- composer scripts ✓ composer qa

### JS/TS + DaisyUI
- eslint.config.js ✓ TS + Vue + Prettier
- .prettierrc       ✓
- vitest.config.ts  ✓ jsdom
- package scripts   ✓ npm run qa
- DaisyUI           ✓ Tailwind + DaisyUI installed
- Theme switch      ✓ useThemeStore + BaseThemeSwitch

### Docker stack (5 containers)
- back             ✓ Symfony 8 / PHP 8.4 / FrankenPHP → http://localhost:8000
- app              ✓ Vue 3 / Vite / DaisyUI           → http://localhost:5173
- db               ✓ PostgreSQL 17                     → localhost:5432
- mailer           ✓ Mailpit                           → http://localhost:8025
- worker           ✓ Messenger consumer + dashboard    → http://localhost:8000/messenger

### Makefile
- Makefile         ✓ make help to list all targets

### Run quality gates
make qa           # runs both PHP and Vue gates
make dev          # start all 5 containers
```

---

## Convention Mode — always active when writing code

Before writing any PHP or Vue code, read the relevant reference:
- PHP/Symfony code → `references/php-conventions.md`
- Vue/TS code → `references/vue-conventions.md`

These are not optional guidelines — apply them to every file touched.

### Non-negotiable rules (quick reference)

**PHP:**
- `final` on every class that isn't meant to be extended
- `readonly class` when all properties are readonly — never `readonly` on individual properties of a `readonly class`
- No getter methods — declare property `public readonly` instead
- No FQCN inline — always `use` imports at the top
- No French anywhere (code, comments, strings)
- No `try/catch` in controllers — a middleware handles all exceptions
- `#[MapRequestPayload]` on every controller that reads a request body
- One handler = one responsibility; all side effects via Domain Events
- Prefer Domain Events over chaining logic in handlers

**Vue:**
- `<script setup lang="ts">` always — never Options API
- Extract to a component whenever a piece of UI could be reused or is logically distinct
- Atomic Design: atoms (`Base*`) → molecules → organisms → templates → pages
- No API calls outside `pages/` level — organisms receive data via props
- No store access in atoms or molecules

---

## QA gate order (mandatory before any PR)

```bash
# PHP
composer phpcbf   # auto-fix style first
composer phpcs    # then check — fix any remaining
composer phpstan  # static analysis — zero errors
composer deptrac  # architecture boundaries — zero violations
php bin/phpunit   # all tests green

# JS/TS
npm run lint      # eslint --fix
npm run type-check # vue-tsc --noEmit
npm run test      # vitest run
```

Never open a PR if any gate fails.
