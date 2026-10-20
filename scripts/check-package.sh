#!/usr/bin/env bash
#
# Build a ZIP from HEAD and check its layout: one "pdfexport/" root, the files
# Moodle needs, and none of the development files. Also checks that building
# twice gives byte-identical archives.
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"
fail() { echo "check-package: ERROR: $1" >&2; exit 1; }

bash scripts/package.sh check >/dev/null
ZIP="$ROOT/block_pdfexport-check.zip"
FIRST="$(shasum -a 256 "$ZIP" | cut -d' ' -f1)"
LIST="$(unzip -Z1 "$ZIP")"

echo "$LIST" | grep -qv '^pdfexport/' && fail "entries outside pdfexport/."
for required in version.php block_pdfexport.php download.php manage.php db/access.php \
        lang/en/block_pdfexport.php lang/es/block_pdfexport.php classes/local/export_service.php \
        classes/privacy/provider.php backup/moodle2/restore_pdfexport_block_task.class.php \
        templates/content.mustache README.md CHANGELOG.md LICENSE; do
    echo "$LIST" | grep -qx "pdfexport/$required" || fail "missing $required."
done
for forbidden in tests/ scripts/ docs/ .github/ vendor/ node_modules/ composer.json Makefile \
        docker-compose.yml blueprint.json AGENTS.md .env; do
    echo "$LIST" | grep -q "^pdfexport/$forbidden" && fail "development path $forbidden is packaged."
done

bash scripts/package.sh check >/dev/null
[ "$(shasum -a 256 "$ZIP" | cut -d' ' -f1)" = "$FIRST" ] || fail "the archive is not reproducible."
rm -f "$ZIP"
echo "check-package: OK ($(echo "$LIST" | wc -l | tr -d ' ') entries, reproducible)."
