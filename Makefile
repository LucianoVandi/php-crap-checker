PHP      = docker compose run --rm php
COMPOSER = docker compose run --rm composer

.PHONY: build install test coverage stan cs-check cs-fix phpmd rector infection check-fixture qa

build:
	HOST_UID=$(shell id -u) HOST_GID=$(shell id -g) docker compose build

install:
	$(COMPOSER) install

test:
	$(PHP) vendor/bin/phpunit

coverage:
	$(PHP) php -d pcov.enabled=1 vendor/bin/phpunit --coverage-crap4j build/crap4j.xml

stan:
	$(PHP) vendor/bin/phpstan analyse src tests

cs-check:
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix:
	$(PHP) vendor/bin/php-cs-fixer fix

phpmd:
	$(PHP) vendor/bin/phpmd src/ text .phpmd.xml

rector:
	$(PHP) vendor/bin/rector process --dry-run

infection:
	$(PHP) vendor/bin/infection

check-fixture:
	$(PHP) php bin/crap-check check tests/Fixtures/crap4j-with-violations.xml --threshold=30

qa: test stan
