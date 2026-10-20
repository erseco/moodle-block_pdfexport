#!/usr/bin/env bash
#
# Build the installable release ZIP from a committed revision.
#
#   bash scripts/package.sh <release> [<git-ref>]    (default ref: HEAD)
#
# The ZIP is made with `git archive`, so it is deterministic for a given commit
# (entries and timestamps come from the commit) and never includes uncommitted
# or untracked files. Development paths are excluded by .gitattributes
# export-ignore rules. Every entry is rooted at "pdfexport/", the folder Moodle
# expects under blocks/. version.php ships exactly as committed: validate it
# first with scripts/check-version.sh.
set -euo pipefail

RELEASE="${1:-}"
REF="${2:-HEAD}"
[ -n "$RELEASE" ] || { echo "Usage: bash scripts/package.sh <release> [<git-ref>]" >&2; exit 1; }
case "$RELEASE" in
    *[!A-Za-z0-9._-]*) echo "Invalid release name: $RELEASE" >&2; exit 1 ;;
esac

ROOT="$(git rev-parse --show-toplevel)"
OUTPUT="$ROOT/block_pdfexport-$RELEASE.zip"
rm -f "$OUTPUT"
git -C "$ROOT" archive --format=zip --prefix=pdfexport/ -o "$OUTPUT" "$REF"
echo "Created $OUTPUT"
