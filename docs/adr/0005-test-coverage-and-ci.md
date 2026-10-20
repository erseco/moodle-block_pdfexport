# ADR-0005: Test coverage and CI strategy

- **Status:** Accepted
- **Date:** 2026-10-20

## Context

The alpha had no automated tests. The plugin handles permissions and untrusted HTML, so
regressions are security issues. `mod_exelearning` (DEC-66-01) learned that pcov
under-reports executed lines and that excluding hard files inflates the figure.

## Decision

- **PHPUnit** (`advanced_testcase`, core generators) covers the domain logic. Coverage
  scope (`tests/coverage.php`): `classes/`, `backup/` and `block_pdfexport.php`. Only
  the thin controllers `manage.php` and `download.php` are outside, covered by Behat.
- **Minimum 90% line coverage**, measured with **Xdebug** on one CI job (PHP 8.3 +
  PostgreSQL) and enforced by `scripts/check-coverage.php` on the Clover report. Codecov
  adds a 90% project minimum, a ratchet (`target: auto`, 0.5% threshold) and a 90% patch
  target; CI's own gate keeps working if Codecov is unavailable.
- **Behat** covers the browser workflows (adding the block, selecting resources,
  downloads, refusals). It is not counted in the coverage figure.
- **Blocking gates**: PHP lint, PHPCS (`--max-warnings 0`), PHPDoc (`--max-warnings 0`),
  plugin validation, savepoints, Mustache lint, PHPUnit, coverage minimum, Behat,
  version metadata and release ZIP layout. PHPMD is advisory.
- Matrix: Moodle 5.3 × PHP 8.3/8.4 × PostgreSQL 17/MariaDB 11.4.

## Alternatives considered

- pcov for speed: rejected (under-reporting, as seen in mod_exelearning).
- Codecov ratchet only: does not enforce an initial minimum.
- Including Behat in coverage: mixes browser flows with unit figures.

## Consequences

Coverage is about 98% at adoption. Security branches (traversal, foreign contexts,
hidden content, cross-course ids) have dedicated tests regardless of the percentage.
90% coverage does not prove correctness; it only shows the code ran under test.

## Risks

Coverage depends on the CI job reaching the PHPUnit step; a failed install fails the
build instead of passing silently.

## Validation

`make coverage` locally; the "Enforce the 90% coverage minimum" step in CI.
