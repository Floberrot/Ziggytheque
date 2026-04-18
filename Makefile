.DEFAULT_GOAL := help

# ── Colors ───────────────────────────────────────────────────────────────────
RESET   := \033[0m
BOLD    := \033[1m
GREEN   := \033[32m
YELLOW  := \033[33m
CYAN    := \033[36m

# ── Docker shortcuts ──────────────────────────────────────────────────────────
DC      := docker compose
BACK    := $(DC) exec back
FRONT   := $(DC) exec app

##@ Infrastructure

.PHONY: dev
dev: ## Start all 5 containers + sync vendor locally
	$(DC) up -d
	@echo "Waiting for back container to be ready..."
	@until $(DC) exec back php bin/console about > /dev/null 2>&1; do sleep 2; done
	@echo "Syncing vendor from container..."
	$(DC) cp back:/app/vendor ./back/vendor-tmp
	@rsync -a --delete ./back/vendor-tmp/ ./back/vendor/
	@rm -rf ./back/vendor-tmp
	@echo "vendor synced."

.PHONY: down
down: ## Stop all containers
	$(DC) down

.PHONY: ps
ps: ## Show container status
	$(DC) ps

.PHONY: logs
logs: ## Tail all container logs
	$(DC) logs -f

.PHONY: logs-back
logs-back: ## Tail backend logs
	$(DC) logs -f back

##@ Backend

.PHONY: cc
cc: ## Clear Symfony cache
	$(BACK) php bin/console cache:clear

.PHONY: migrate
migrate: ## Run Doctrine migrations
	$(BACK) php bin/console doctrine:migrations:migrate --no-interaction

.PHONY: migration
migration: ## Generate a new migration
	$(BACK) php bin/console doctrine:migrations:diff

.PHONY: fixtures
fixtures: ## Load fixtures
	$(BACK) php bin/console doctrine:fixtures:load --no-interaction

.PHONY: jwt-keys
jwt-keys: ## Generate JWT keypair
	$(BACK) php bin/console lexik:jwt:generate-keypair --overwrite

.PHONY: php-qa
php-qa: ## Run all PHP quality gates
	$(BACK) composer qa

.PHONY: phpstan
phpstan: ## Run PHPStan
	$(BACK) composer phpstan

.PHONY: phpcs
phpcs: ## Run PHPCS
	$(BACK) composer phpcs

.PHONY: phpcbf
phpcbf: ## Auto-fix PHP code style
	$(BACK) composer phpcbf

.PHONY: test-php
test-php: ## Run PHP tests
	$(BACK) composer test

.PHONY: logs-worker
logs-worker: ## Tail Messenger worker logs
	$(DC) logs -f worker

.PHONY: shell-back
shell-back: ## Open shell in back container
	$(BACK) sh

##@ Frontend

.PHONY: vue-qa
vue-qa: ## Run all Vue quality gates
	$(FRONT) npm run qa

.PHONY: lint
lint: ## Lint & fix frontend
	$(FRONT) npm run lint

.PHONY: type-check
type-check: ## Run vue-tsc type check
	$(FRONT) npm run type-check

.PHONY: test-vue
test-vue: ## Run Vitest tests
	$(FRONT) npm run test

.PHONY: shell-front
shell-front: ## Open shell in app container
	$(FRONT) sh

##@ Quality

.PHONY: deptrac
deptrac: ## Run Deptrac architecture boundary check
	$(BACK) vendor/bin/deptrac analyse

.PHONY: php-qa
php-qa: ## Run all PHP quality gates (style + stan + deptrac + tests)
	$(BACK) composer qa
	$(MAKE) deptrac

.PHONY: qa
qa: php-qa vue-qa ## Run all quality gates (PHP + Vue)

##@ Setup

.PHONY: install
install: ## Install all dependencies (local)
	cd back && composer install
	cd front && npm install

.PHONY: setup
setup: ## First-time setup: start containers, wait for back, generate JWT keys, migrate, seed price codes
	$(DC) up -d
	@echo "Waiting for back container to start..."
	@until docker compose exec back php -r "echo 'ok';" > /dev/null 2>&1; do sleep 2; done
	@echo "Running composer install..."
	docker compose exec back composer install --no-interaction --prefer-dist
	$(MAKE) jwt-keys
	@echo "Waiting for back container to be ready..."
	@until docker compose exec back php bin/console about > /dev/null 2>&1; do sleep 2; done
	@echo "Dropping existing schema..."
	docker compose exec back php bin/console doctrine:schema:drop --force --full-database
	$(MAKE) migrate

##@ Help

.PHONY: help
help: ## Display this help
	@awk 'BEGIN {FS = ":.*##"; printf "\n$(BOLD)Usage:$(RESET)\n  make $(CYAN)<target>$(RESET)\n"} /^[a-zA-Z_0-9-]+:.*?##/ { printf "  $(CYAN)%-18s$(RESET) %s\n", $$1, $$2 } /^##@/ { printf "\n$(BOLD)%s$(RESET)\n", substr($$0, 5) } ' $(MAKEFILE_LIST)
