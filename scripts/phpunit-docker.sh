#!/bin/sh
# Run the block_pdfexport PHPUnit suite inside the alpine-moodle container.
#
# Idempotent: the first run wires PHPUnit into the container's Moodle (test
# config, dev dependencies, test database); every run then executes the suite.
# Invoked from the host by `make test` / `make coverage`.
set -e

MOODLE=/var/www/html
CONFIG="$MOODLE/config.php"
PHPUNITDATA=/var/www/phpunitdata

if ! grep -q phpunit_prefix "$CONFIG"; then
    echo "[phpunit-docker] adding PHPUnit config to config.php"
    awk '!ins && /lib\/setup\.php/ {
        print "$CFG->phpunit_prefix = \"t_\";";
        print "$CFG->phpunit_dataroot = \"/var/www/phpunitdata\";";
        print "";
        ins = 1
    } { print }' "$CONFIG" > "$CONFIG.tmp"
    mv "$CONFIG.tmp" "$CONFIG"
fi
mkdir -p "$PHPUNITDATA"

cd "$MOODLE"
# Composer (also called by init.php) needs a writable home as the nobody user.
export COMPOSER_HOME=/tmp/composer COMPOSER_CACHE_DIR=/tmp/composer-cache
if [ ! -x vendor/bin/phpunit ]; then
    echo "[phpunit-docker] installing Moodle dev dependencies (one-time)..."
    composer install --no-interaction --no-progress
fi

# (Re)initialise when missing or outdated (new plugin version, other plugins upgraded...).
if [ ! -f phpunit.xml ] || ! php public/admin/tool/phpunit/cli/util.php --diag >/dev/null 2>&1; then
    echo "[phpunit-docker] initialising the PHPUnit test environment..."
    php public/admin/tool/phpunit/cli/init.php
fi

if [ "$#" -eq 0 ]; then
    set -- --testsuite block_pdfexport_testsuite
fi
echo "[phpunit-docker] running: vendor/bin/phpunit $*"
exec php vendor/bin/phpunit "$@"
