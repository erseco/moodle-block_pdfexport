# Development

## Requirements

- Docker with Compose v2.
- PHP 8.3+ and Composer on the host, only for `make lint` / `make fix`.
- Optional: Node.js and Google Chrome for `scripts/screenshots.mjs`; poppler
  (`pdftoppm`) to render PDF pages.

## Local Moodle

```bash
make up      # Moodle 5.3 + MariaDB 11.4; first start installs Moodle (a few minutes)
make demo    # "PDF Export Demo Course": teacher_demo / student_demo, password Demo!2026
make logs    # follow the container log
make down    # stop; `make clean` also deletes the volumes
```

The checkout is mounted at `public/blocks/pdfexport` (Moodle 5.1+ layout). Settings
are in `.env` (copied from `.env.dist`): admin user, image tag and port. With a port
other than 80, set `REVERSEPROXY=true` and include the port in `SITE_URL`.

## Checks

| Command | What it runs |
|---|---|
| `make lint` / `make fix` | PHPCS / PHPCBF with the `moodle` standard (`.phpcs.xml.dist`) |
| `make test` | PHPUnit (`scripts/phpunit-docker.sh`; `ARGS=public/blocks/pdfexport/tests/<file>.php` for one file) |
| `make coverage` | PHPUnit with Xdebug; prints per-class coverage and fails under 90% |
| `make behat` | Behat `@block_pdfexport` with headless Chromium in the container |
| `make plugin-ci` | moodle-plugin-ci: phplint, phpcs, validate, savepoints, phpdoc, mustache |
| `make check-version` | `version.php` rules (`RELEASE=1.0.0` for release rules) |
| `make check-package` | builds a ZIP from HEAD, checks layout and reproducibility |

The first run of `test`, `coverage`, `behat` and `plugin-ci` installs what it needs
inside the container (Moodle dev dependencies, Xdebug, Chromium, moodle-plugin-ci) and
initialises the test sites; later runs reuse them. PHPUnit re-initialises automatically
when the plugin version changes.

CI (`.github/workflows/ci.yml`) runs the same checks with moodle-plugin-ci on
Moodle 5.3 × PHP 8.3/8.4 × PostgreSQL 17/MariaDB 11.4, plus PHPMD (advisory).

## Coverage

`tests/coverage.php` limits the figure to `classes/`, `backup/` and
`block_pdfexport.php`. Do not exclude code because it is hard to test: mock or build
fixtures instead (ADR-0005). `scripts/check-coverage.php <clover.xml> 90` counts only
`blocks/pdfexport/` files of a Clover report.

## Versioning and releases

- `$plugin->version` is a real `YYYYMMDDXX`; bump it when Moodle must notice a change
  (new strings, capabilities, templates or code that caches). `$plugin->release` stays
  `'dev'` on `main`.
- To release `X.Y.Z`: open a PR that sets `$plugin->release = 'X.Y.Z'`, bumps the
  version and moves the *Unreleased* changelog entries under `X.Y.Z`. After merging,
  create the GitHub release with tag `vX.Y.Z`; `release.yml` runs CI, validates the
  metadata and attaches `block_pdfexport-X.Y.Z.zip`. Then set `release` back to `'dev'`.
- `make package RELEASE=X.Y.Z` builds the same ZIP locally from HEAD.
- To test an installable ZIP without publishing, run the *Release* workflow manually
  (`workflow_dispatch`): it uploads the ZIP as an artifact.

## Moodle Playground

`blueprint.json` builds the demo course in [Moodle Playground](https://moodle-playground.com)
(Moodle 5.3, PHP 8.3) from the `main` branch archive. Validate it against the
[schema](https://raw.githubusercontent.com/ateeducacion/moodle-playground/refs/heads/main/assets/blueprints/blueprint-schema.json).
Pull requests get a preview button from `pr-playground-preview.yml`, which points the
blueprint at the PR branch.

To try local, unpushed code: build a ZIP (`git archive --format=zip --prefix=pdfexport/ -o /tmp/serve/pdfexport.zip HEAD`),
copy `blueprint.json` to `/tmp/serve` with the `installMoodlePlugin` URL changed to
`http://localhost:8765/pdfexport.zip`, serve that folder with CORS headers
(`Access-Control-Allow-Origin: *`, `Access-Control-Allow-Private-Network: true`) and open
`https://moodle-playground.com/?blueprint-url=http://localhost:8765/blueprint.json`.

## Screenshots

With `make up && make demo` running:

```bash
npm install --no-save --prefix /tmp/pptr puppeteer-core
NODE_PATH=/tmp/pptr/node_modules MOODLE_URL=http://localhost node scripts/screenshots.mjs
cd docs/screenshots
pdftoppm -r 60 -png -f 1 -l 3 course-export.pdf pdf-course
pdftoppm -r 60 -png -f 1 -l 1 resource-export.pdf pdf-resource && rm resource-export.pdf
```

## Performance check

The ADR-0004 figures come from a generated course with 300 pages, each with a
1200×800 JPEG, exported with `export_service` from the CLI. Re-run the measurement when
changing the transformer or the renderer.
