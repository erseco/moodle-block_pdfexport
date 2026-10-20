# ADR-0006: Release and compatibility policy

- **Status:** Accepted
- **Date:** 2026-10-20

## Context

The alpha declared `requires = 2026100200` (Moodle 5.3 development builds) and used
hand-edited release names. Releases need reproducible, installable ZIPs and metadata
Moodle can upgrade from.

## Decision

- **Supported Moodle**: 5.3 LTS only (`requires = 2026100200`, `supported = [503, 503]`),
  PHP 8.3–8.4, PostgreSQL 17+, MariaDB 11.4+ (Moodle 5.3 minimums). The minimum is not
  raised silently, and other versions are claimed only after CI covers them.
- **Versions**: `$plugin->version` is a real, monotonic `YYYYMMDDXX`. On `main`,
  `$plugin->release = 'dev'`. A release PR sets the semantic release (`1.0.0`,
  `1.1.0-beta`) and bumps the version; the tag is `v` + release.
- **Packaging**: `scripts/package.sh` uses `git archive` with `.gitattributes`
  `export-ignore` rules: a deterministic ZIP rooted at `pdfexport/`, without tests,
  scripts, docs or tooling. `scripts/check-package.sh` checks layout and
  reproducibility in CI.
- **Publication**: `release.yml` runs the full CI as a required job, validates
  `version.php` against the tag, and attaches the ZIP to the published GitHub release.
  `workflow_dispatch` only builds an artifact. Pull requests never publish.
- Changes are listed in `CHANGELOG.md` (Keep a Changelog).

## Alternatives considered

- Release automation that rewrites `version.php` when building (as in older
  mod_exelearning flows): the shipped file would differ from the reviewed one.
- Supporting Moodle 4.5–5.2: the plugin uses 5.x APIs (delegated sections, public
  directory) and has never been tested there.

## Consequences

A release is: PR with version/release/changelog → merge → create the GitHub release
`vX.Y.Z` → the workflow attaches the ZIP. Then a PR sets `release` back to `dev`.

## Risks

Forgetting to return to `dev` after a release: `check-version.sh` in CI fails on `main`
if `release` is neither `dev` nor matching the commit's own release.

## Validation

`make check-version`, `make check-package`, and the `package` CI job.
