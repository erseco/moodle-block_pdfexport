# Makefile for the block_pdfexport Moodle plugin.
#
# Docker targets use the stack in docker-compose.yml (Moodle 5.3 + MariaDB 11.4)
# with this checkout mounted at public/blocks/pdfexport. Run `make up` first.

COMPOSE := docker compose
EXEC := $(COMPOSE) exec -T moodle
EXEC_ROOT := $(COMPOSE) exec -T -u root moodle
PLUGIN := /var/www/html/public/blocks/pdfexport
PLUGIN_CI := /tmp/ci/bin/moodle-plugin-ci

.DEFAULT_GOAL := help
.PHONY: help check-docker env up down clean logs shell demo install-deps lint fix test coverage behat \
	plugin-ci check-version check-package package

help:
	@echo "Available commands:"
	@echo "  up             - Start Moodle 5.3 + MariaDB in the background (http://localhost:\$$MOODLE_PORT)"
	@echo "  down           - Stop the containers"
	@echo "  clean          - Stop the containers and delete their volumes"
	@echo "  logs           - Follow the Moodle container logs"
	@echo "  shell          - Open a shell in the Moodle container"
	@echo "  demo           - Create the 'PDF Export Demo Course' in the local Moodle"
	@echo "  install-deps   - Install the Composer development tools (moodle-cs)"
	@echo "  lint           - Moodle coding style (PHPCS, zero errors and warnings)"
	@echo "  fix            - Fix coding style automatically (PHPCBF)"
	@echo "  test           - PHPUnit suite in the container (ARGS=<test file> for one file)"
	@echo "  coverage       - PHPUnit with Xdebug coverage, enforcing the 90% minimum"
	@echo "  behat          - Behat features in the container with headless Chromium"
	@echo "  plugin-ci      - moodle-plugin-ci phplint, phpcs, phpdoc, validate, savepoints, mustache"
	@echo "  check-version  - Validate version.php (RELEASE=X.Y.Z for release rules)"
	@echo "  check-package  - Build a ZIP from HEAD and validate its layout"
	@echo "  package        - Build block_pdfexport-RELEASE.zip from HEAD (RELEASE=X.Y.Z)"

check-docker:
	@docker version >/dev/null 2>&1 || { echo "Error: Docker is not running."; exit 1; }

env:
	@[ -f .env ] || { echo "Creating .env from .env.dist"; cp .env.dist .env; }

up: check-docker env
	$(COMPOSE) up -d

down: check-docker env
	$(COMPOSE) down

clean: check-docker env
	$(COMPOSE) down -v --remove-orphans

logs: check-docker
	$(COMPOSE) logs -f moodle

shell: check-docker
	$(COMPOSE) exec moodle sh

demo: check-docker
	$(EXEC) php $(PLUGIN)/scripts/setup_demo.php

install-deps:
	composer install --no-interaction --no-progress

lint: install-deps
	vendor/bin/phpcs

fix: install-deps
	vendor/bin/phpcbf || true

test: check-docker
	$(EXEC) sh $(PLUGIN)/scripts/phpunit-docker.sh $(ARGS)

# Xdebug is installed (once) as root; it is only loaded for this run.
coverage: check-docker
	$(EXEC_ROOT) sh -c 'php -m | grep -q xdebug || [ -f /usr/lib/php*/modules/xdebug.so ] || apk add --no-cache php84-pecl-xdebug >/dev/null'
	$(EXEC) sh $(PLUGIN)/scripts/phpunit-docker.sh --list-suites >/dev/null
	$(EXEC) php -d zend_extension=xdebug.so -d xdebug.mode=coverage vendor/bin/phpunit \
		--testsuite block_pdfexport_testsuite --coverage-text --coverage-clover /tmp/block_pdfexport-clover.xml \
		| sed -n '/Code Coverage Report/,$$p' | grep -A1 'block_pdfexport\|restore_pdfexport' || true
	$(EXEC) php $(PLUGIN)/scripts/check-coverage.php /tmp/block_pdfexport-clover.xml 90

# Chromium and chromedriver are installed (once) as root.
behat: check-docker
	$(EXEC_ROOT) sh -c 'command -v chromedriver >/dev/null || apk add --no-cache chromium chromium-chromedriver >/dev/null'
	$(EXEC) sh $(PLUGIN)/scripts/behat-docker.sh $(ARGS)

# moodle-plugin-ci is installed (once) under /tmp/ci; git, Java and Node as root.
plugin-ci: check-docker
	$(EXEC_ROOT) sh -c 'command -v git >/dev/null && command -v java >/dev/null && command -v npm >/dev/null || apk add --no-cache git openjdk17-jre-headless nodejs npm >/dev/null'
	$(EXEC) sh -c 'export HOME=/tmp COMPOSER_HOME=/tmp/composer; [ -x $(PLUGIN_CI) ] || { composer create-project -n --no-dev --prefer-dist moodlehq/moodle-plugin-ci /tmp/ci ^4 && cd /tmp/ci/vendor/moodlehq/moodle-local_ci && npm install --no-progress; }'
	$(EXEC) sh -c '[ -d public/local/moodlecheck ] || { git clone -q --depth 1 https://github.com/moodlehq/moodle-local_moodlecheck.git public/local/moodlecheck && php admin/cli/upgrade.php --non-interactive >/dev/null; }'
	$(EXEC) sh -c 'set -e; for c in phplint "phpcs --max-warnings 0" validate savepoints; do $(PLUGIN_CI) $$c $(PLUGIN); done; \
		$(PLUGIN_CI) phpdoc --max-warnings 0 --moodle=/var/www/html $(PLUGIN); $(PLUGIN_CI) mustache --moodle=/var/www/html $(PLUGIN)'

check-version:
	bash scripts/check-version.sh $(if $(RELEASE),--release $(RELEASE))

check-package:
	bash scripts/check-package.sh

package:
	@[ -n "$(RELEASE)" ] || { echo "Usage: make package RELEASE=X.Y.Z"; exit 1; }
	bash scripts/check-version.sh --release $(RELEASE)
	bash scripts/package.sh $(RELEASE)
