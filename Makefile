PHP      = docker compose run --rm php
COMPOSER = docker compose run --rm composer

.PHONY: build install test coverage crap stan cs-check cs-fix phpmd rector infection check-fixture composer-audit composer-unused composer-normalize qa shell help

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*##' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*##"}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

build: ## Build Docker image
	HOST_UID=$(shell id -u) HOST_GID=$(shell id -g) docker compose build

install: ## Install Composer dependencies
	$(COMPOSER) install

test: ## Run PHPUnit test suite
	$(PHP) vendor/bin/phpunit

coverage: ## Generate Crap4J coverage report
	$(PHP) php -d pcov.enabled=1 vendor/bin/phpunit --coverage-crap4j build/crap4j.xml

crap: coverage ## Run crap-check on the generated coverage report
	$(PHP) php bin/crap-check check build/crap4j.xml --threshold=30

stan: ## Run PHPStan static analysis (level 9)
	$(PHP) vendor/bin/phpstan analyse src tests --memory-limit=256M

cs-check: ## Check code style (dry-run)
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Fix code style
	$(PHP) vendor/bin/php-cs-fixer fix

phpmd: ## Run PHP Mess Detector
	$(PHP) vendor/bin/phpmd src/ text .phpmd.xml

rector: ## Run Rector (dry-run)
	$(PHP) vendor/bin/rector process --dry-run

infection: ## Run mutation testing with Infection
	$(PHP) vendor/bin/infection

check-fixture: ## Run crap-check against the violations fixture (expects exit 1)
	$(PHP) php bin/crap-check check tests/Fixtures/crap4j-with-violations.xml --threshold=30

composer-audit: ## Check dependencies for known security vulnerabilities
	$(COMPOSER) audit

composer-unused: ## Check for unused Composer dependencies
	$(PHP) vendor/bin/composer-unused

composer-normalize: ## Check canonical formatting of composer.json (dry-run)
	$(COMPOSER) normalize --dry-run --diff

qa: test stan ## Run minimum CI gate (test + stan)

shell: ## Open a bash shell in the PHP container
	docker compose run --rm php bash
