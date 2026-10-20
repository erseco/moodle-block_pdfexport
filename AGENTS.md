# block_pdfexport

Moodle course block: teachers publish course resources; users download them as PDF,
one resource or the whole selection as a single course PDF. PDFs are built with
Moodle's bundled TCPDF. Created by HenarLG (0.x alpha); keep her attribution.

Supported: Moodle 5.3 LTS only (`version.php`: `requires = 2026100200`,
`supported = [503, 503]`), PHP 8.3–8.4, PostgreSQL 17+, MariaDB 11.4+.

## Orientation

- Entry points: `block_pdfexport.php` (block content), `manage.php` (selection form),
  `download.php` (PDF). Controllers stay thin; logic lives in `classes/`.
- `classes/local/export_service.php` is the only export pipeline (course and single
  resource): `resource_detector` → `adapters/*` → `print_transformer` → `pdf_document`.
- `classes/local/publication.php`: block config (`selectedcmids`, alpha format) and
  block-belongs-to-course validation. Restore remapping: `backup/moodle2/`.
- Read [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) before structural changes and the
  [ADRs](docs/adr/) for the reasons. Add an ADR for new architectural decisions; do
  not rewrite accepted ones.
- Moodle APIs: verify against Moodle 5.3 (Context7 `/moodle/devdocs`,
  https://moodledev.io/docs/5.3, or the `MOODLE_503_STABLE` source). Never invent
  functions or signatures. Avoid APIs deprecated in 5.3 (tests fail on deprecation
  debugging).

## Project rules

- Never bypass Moodle permission checks. Export lists are rebuilt from the current
  user's modinfo on every request; never trust `courseid`, `blockid` or `cmid`.
  Unpublished, hidden, restricted and unknown modules must get the same error.
- Never export restricted content: no attempts, grades, submissions, posts or
  author-hidden HTML. Quiz, assign, forum and similar modules stay unexportable.
- Never fetch remote media or URLs while exporting; images come only from the exported
  module's own file area, inline (`@` + base64).
- Do not add an exporter (adapter) without tests and docs (README table, ADR-0002).
- Do not duplicate PDF generation: everything goes through `export_service::render()`
  and `pdf_document`.
- Do not exclude testable code from coverage (`tests/coverage.php`); keep ≥ 90%.
- Do not raise the Moodle minimum or claim new versions without CI coverage.
- Do not publish releases, push or merge without explicit authorization; never
  publish without the quality gates passing.
- Code, comments, docs and commits in English. User-facing text only through language
  strings, with English and Spanish (`lang/en`, `lang/es`) kept in alphabetical order.

## Coding standards

Moodle coding style (not PSR-12/WordPress): `vendor/bin/phpcs` must report 0 errors and
0 warnings; complete PHPDoc on every class, method, property and constant; typed
parameters and returns; `block_pdfexport\…` namespaces with Moodle autoloading.

## Verify changes

```bash
make lint            # PHPCS
make test            # PHPUnit (Docker; `make up` first)
make coverage        # Xdebug, fails under 90%
make behat           # headless Chromium
make plugin-ci       # phplint, phpdoc, validate, savepoints, mustache
make check-version && make check-package
```

Behaviour changes need PHPUnit tests for the happy path and the security edge cases;
UI workflow changes need Behat. Documentation-only changes need no tests.

## CI/CD and releases

- `.github/workflows/ci.yml`: moodle-plugin-ci on Moodle 5.3 × PHP 8.3/8.4 ×
  PostgreSQL/MariaDB; Xdebug coverage job with the 90% gate; package job.
- `release.yml`: on a published GitHub release, runs CI, validates `version.php`
  against the tag, attaches the ZIP. Manual runs only build an artifact.
- `pr-playground-preview.yml` + `blueprint.json`: Moodle Playground demo.
- `version.php`: real monotonic `YYYYMMDDXX`; `release = 'dev'` on `main`; semantic
  `X.Y.Z` only in release commits (ADR-0006, DEVELOPMENT.md).

Update this file when architecture, commands or rules change.
