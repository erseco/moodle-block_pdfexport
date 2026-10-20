#!/usr/bin/env bash
#
# Validate version.php (see ADR-0006).
#
#   bash scripts/check-version.sh                  # development rules
#   bash scripts/check-version.sh --release 1.0.0  # release rules
#
# Development: $plugin->version is a real YYYYMMDDXX value and
# $plugin->release is 'dev'. Release: $plugin->release equals the semantic
# version given (without "v"), is not 'dev', and, when HEAD carries an exact
# tag, the tag is "v" + that version. In both modes $plugin->requires must be
# a Moodle version (YYYYMMDDXX) and the plugin version must not go backwards
# from the latest release tag. CHECK_VERSION_FILE overrides the file (tests).
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
FILE="${CHECK_VERSION_FILE:-$ROOT/version.php}"
fail() { echo "check-version: ERROR: $1" >&2; exit 1; }

EXPECTED=""
if [ "${1:-}" = "--release" ]; then
    EXPECTED="${2:-}"
    [ -n "$EXPECTED" ] || fail "--release requires a version, e.g. --release 1.0.0."
    EXPECTED="${EXPECTED#v}"
elif [ -n "${1:-}" ]; then
    fail "unknown argument '$1'."
fi

field() { sed -n "s/^\$plugin->$1[[:space:]]*=[[:space:]]*\(.*\);.*/\1/p" "$FILE" | head -n1 | tr -d "'"; }
VERSION="$(field version)"
REQUIRES="$(field requires)"
RELEASE="$(field release)"

echo "$VERSION" | grep -qE '^20[0-9]{2}(0[1-9]|1[0-2])(0[1-9]|[12][0-9]|3[01])[0-9]{2}$' \
    || fail "\$plugin->version '$VERSION' is not a YYYYMMDDXX value."
echo "$REQUIRES" | grep -qE '^20[0-9]{8}(\.[0-9]{2})?$' || fail "\$plugin->requires '$REQUIRES' is not a Moodle version."

if [ -z "$EXPECTED" ]; then
    [ "$RELEASE" = "dev" ] || fail "\$plugin->release is '$RELEASE'; it must be 'dev' outside a release commit."
else
    echo "$EXPECTED" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+(-(alpha|beta|rc)(\.[0-9]+)?)?$' \
        || fail "release '$EXPECTED' is not a semantic version."
    [ "$RELEASE" = "$EXPECTED" ] || fail "\$plugin->release is '$RELEASE', expected '$EXPECTED'."
    TAG="${CHECK_VERSION_TAG-$(git -C "$ROOT" describe --tags --exact-match 2>/dev/null || true)}"
    if [ -n "$TAG" ] && [ "$TAG" != "v$EXPECTED" ]; then
        fail "HEAD is tagged '$TAG', expected 'v$EXPECTED'."
    fi
fi

# Monotonic: never below the version of the latest release tag reachable from HEAD.
LAST_TAG="$(git -C "$ROOT" describe --tags --abbrev=0 --match 'v[0-9]*' 2>/dev/null || true)"
if [ -n "$LAST_TAG" ] && [ -z "${CHECK_VERSION_FILE:-}" ]; then
    LAST="$(git -C "$ROOT" show "$LAST_TAG:version.php" | sed -n 's/^\$plugin->version[[:space:]]*=[[:space:]]*\([0-9]*\);.*/\1/p')"
    if [ -n "$LAST" ] && [ "$VERSION" -lt "$LAST" ]; then
        fail "\$plugin->version $VERSION is lower than $LAST_TAG ($LAST)."
    fi
fi
echo "check-version: OK (version $VERSION, release '$RELEASE'${EXPECTED:+, expected $EXPECTED})."
