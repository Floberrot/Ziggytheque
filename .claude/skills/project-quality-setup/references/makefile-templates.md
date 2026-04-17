# Makefile Templates

Generate a `Makefile` at the project root during every setup. Adapt targets to detected
project type (PHP-only, JS/TS-only, or full-stack monorepo with Docker).

---

## Rules for writing the Makefile

- Always add `.PHONY` for every non-file target
- Use `@` prefix to suppress echoing commands; add a short `## comment` for `make help`
- Targets call into sub-directories (`cd back && …` or `cd front && …`) — never assume CWD
- Docker targets always wrap `docker compose` (v2 syntax, no hyphen)
- Include a `help` target that prints all `## …` comments — it must be the default target
- Detect if running in Docker or locally: `docker compose exec api` vs `cd back`
- Group targets visually with section comments (`##@ Section`)

**IMPORTANT — read `references/docker-gotchas.md` before writing any Dockerfile or
docker-compose.yml.** Known traps:
- FrankenPHP tag is `php8.4-alpine`, never `latest-php8.4-alpine`
- `docker-compose.yml` must NOT build the production Dockerfile — use the base image directly
  with mounted volumes so a missing `composer.lock` never breaks a fresh `make up`

---

## Full-stack monorepo template (back/ + front/ + Docker — 5 containers)

Standard stack: Symfony 8 + PHP 8.4 (back) · Vue 3 + DaisyUI (app/front) · PostgreSQL (db) · Mailpit (mailer) · Messenger worker (worker)

```makefile
.DEFAULT_GOAL := help

##@ Help
help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z_0-9-]+:.*?##/ { printf "  \033[36m%-22s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

##@ Docker
up: ## Start all 5 containers (detached)
	docker compose up -d

up-build: ## Rebuild images and start
	docker compose up -d --build

down: ## Stop and remove containers
	docker compose down

down-v: ## Stop and remove containers + volumes (⚠ destroys DB data)
	docker compose down -v

restart: ## Restart all containers
	docker compose restart

logs: ## Follow all container logs
	docker compose logs -f

logs-back: ## Follow back container logs
	docker compose logs -f back

logs-worker: ## Follow worker container logs
	docker compose logs -f worker

ps: ## Show container status
	docker compose ps

shell-back: ## Open shell in back container
	docker compose exec back sh

shell-db: ## Open psql in database container
	docker compose exec db psql -U app

##@ Setup
install: ## Install all dependencies (PHP + Node)
	cd back && composer install
	cd front && npm install

install-back: ## Install PHP dependencies
	cd back && composer install

install-front: ## Install Node dependencies
	cd front && npm install

jwt-keys: ## Generate JWT key pair
	mkdir -p back/config/jwt
	openssl genpkey -algorithm RSA -out back/config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096
	openssl rsa -pubout -in back/config/jwt/private.pem -out back/config/jwt/public.pem
	@echo "JWT keys generated in back/config/jwt/"

##@ Database
db-create: ## Create database
	cd back && php bin/console doctrine:database:create --if-not-exists

db-migrate: ## Run pending migrations
	cd back && php bin/console doctrine:migrations:migrate --no-interaction

db-fresh: ## Drop, create, and migrate database (⚠ destroys local data)
	docker compose down -v
	docker compose up -d db
	sleep 3
	cd back && php bin/console doctrine:migrations:migrate --no-interaction

db-diff: ## Generate a new migration from entity changes
	cd back && php bin/console doctrine:migrations:diff

db-validate: ## Validate Doctrine mapping
	cd back && php bin/console doctrine:schema:validate

##@ Quality — PHP
qa: qa-back qa-front ## Run all QA gates (PHP + Vue)

qa-back: ## Run all PHP QA gates
	cd back && composer qa

cs-fix: ## Auto-fix PHP code style (phpcbf)
	cd back && composer phpcbf

cs: ## Check PHP code style (phpcs)
	cd back && composer phpcs

stan: ## Run PHPStan static analysis
	cd back && composer phpstan

deptrac: ## Check hexagonal architecture boundaries
	cd back && composer deptrac

test: ## Run all PHP tests
	cd back && composer test

test-unit: ## Run PHP unit tests only
	cd back && php bin/phpunit --testsuite Unit

test-integration: ## Run PHP integration tests only
	cd back && php bin/phpunit --testsuite Integration

test-functional: ## Run PHP functional tests only
	cd back && php bin/phpunit --testsuite Functional

##@ Quality — Vue
qa-front: ## Run all Vue QA gates
	cd front && npm run qa

lint: ## Lint and auto-fix Vue/TS files
	cd front && npm run lint

lint-check: ## Lint Vue/TS files without fixing
	cd front && npm run lint:check

type-check: ## Run TypeScript type check
	cd front && npm run type-check

test-front: ## Run Vitest tests
	cd front && npm run test

test-watch: ## Run Vitest in watch mode
	cd front && npm run test:watch

##@ Dev
dev: up ## Alias for make up
	@echo "Services running:"
	@echo "  Back (Symfony): http://localhost:8000"
	@echo "  App (Vue 3):    http://localhost:5173"
	@echo "  Mailer (UI):    http://localhost:8025"
	@echo "  Messenger:      http://localhost:8000/messenger"
	@echo "  DB (psql):      localhost:5432"

.PHONY: help up up-build down down-v restart logs logs-back logs-worker ps shell-back shell-db \
        install install-back install-front jwt-keys \
        db-create db-migrate db-fresh db-diff db-validate \
        qa qa-back cs-fix cs stan deptrac test test-unit test-integration test-functional \
        qa-front lint lint-check type-check test-front test-watch dev
```

---

## PHP-only template (no Docker, no frontend)

```makefile
.DEFAULT_GOAL := help

help:
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z_0-9-]+:.*?##/ { printf "  \033[36m%-22s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

##@ Setup
install: ## Install PHP dependencies
	composer install

jwt-keys: ## Generate JWT key pair
	mkdir -p config/jwt
	openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096
	openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem

##@ Quality
qa: cs-fix cs stan deptrac test ## Run all QA gates

cs-fix: ## Auto-fix code style
	composer phpcbf

cs: ## Check code style
	composer phpcs

stan: ## Static analysis
	composer phpstan

deptrac: ## Architecture boundaries
	composer deptrac

test: ## Run all tests
	composer test

test-unit: ## Unit tests only
	php bin/phpunit --testsuite Unit

test-integration: ## Integration tests only
	php bin/phpunit --testsuite Integration

test-functional: ## Functional tests only
	php bin/phpunit --testsuite Functional

.PHONY: help install jwt-keys qa cs-fix cs stan deptrac test test-unit test-integration test-functional
```

---

## JS/TS-only template (no Docker, no PHP)

```makefile
.DEFAULT_GOAL := help

help:
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z_0-9-]+:.*?##/ { printf "  \033[36m%-22s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

##@ Setup
install: ## Install Node dependencies
	npm install

##@ Quality
qa: lint type-check test ## Run all QA gates

lint: ## Lint and auto-fix
	npm run lint

lint-check: ## Lint without fixing
	npm run lint:check

type-check: ## TypeScript type check
	npm run type-check

test: ## Run Vitest
	npm run test

test-watch: ## Vitest watch mode
	npm run test:watch

##@ Dev
dev: ## Start dev server
	npm run dev

build: ## Production build
	npm run build

.PHONY: help install qa lint lint-check type-check test test-watch dev build
```
