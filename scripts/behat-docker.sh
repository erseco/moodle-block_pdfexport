#!/bin/sh
# Run the block_pdfexport Behat features inside the alpine-moodle container,
# with headless Chromium (installed by `make behat`, as root, before this runs).
#
# Idempotent: the first run adds the Behat settings to config.php and
# initialises the Behat site; every run starts chromedriver and runs the
# @block_pdfexport features. The Behat site is served by the container's own
# nginx at http://127.0.0.1:8080, separate from the development wwwroot.
set -e

MOODLE=/var/www/html
CONFIG="$MOODLE/config.php"
cd "$MOODLE"
export COMPOSER_HOME=/tmp/composer COMPOSER_CACHE_DIR=/tmp/composer-cache HOME=/tmp

if ! grep -q behat_wwwroot "$CONFIG"; then
    echo "[behat-docker] adding Behat config to config.php"
    awk '!ins && /lib\/setup\.php/ {
        print "$CFG->behat_wwwroot = \"http://127.0.0.1:8080\";";
        print "$CFG->behat_dataroot = \"/var/www/behatdata\";";
        print "$CFG->behat_prefix = \"b_\";";
        print "$CFG->behat_profiles = [\"headlesschrome\" => [\"browser\" => \"chrome\", \"wd_host\" => \"http://127.0.0.1:4444\",";
        print "    \"capabilities\" => [\"extra_capabilities\" => [\"goog:chromeOptions\" => [\"args\" => [\"--headless=new\",";
        print "    \"--no-sandbox\", \"--disable-dev-shm-usage\", \"--window-size=1366,1000\"]]]]]];";
        print "";
        ins = 1
    } { print }' "$CONFIG" > "$CONFIG.tmp"
    mv "$CONFIG.tmp" "$CONFIG"
fi
mkdir -p /var/www/behatdata

STAMP="$MOODLE/.block_pdfexport_behat_version"
CURRENT="$(cat public/blocks/pdfexport/version.php public/blocks/pdfexport/tests/behat/*.php | md5sum)"
if [ "$(cat "$STAMP" 2>/dev/null)" != "$CURRENT" ]; then
    echo "[behat-docker] initialising the Behat site..."
    php public/admin/tool/behat/cli/init.php
    echo "$CURRENT" > "$STAMP"
fi

if ! wget -q -O /dev/null http://127.0.0.1:4444/status 2>/dev/null; then
    chromedriver --port=4444 --allowed-ips= >/tmp/chromedriver.log 2>&1 &
    sleep 2
fi

if [ "$#" -eq 0 ]; then
    set -- --tags=@block_pdfexport
fi
exec php vendor/bin/behat --config /var/www/behatdata/behatrun/behat/behat.yml --profile=headlesschrome "$@"
