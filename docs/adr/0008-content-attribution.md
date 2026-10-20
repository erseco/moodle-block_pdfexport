# ADR-0008: Content authorship and license

- **Status:** Accepted
- **Date:** 2026-10-20

## Context

Exported PDFs leave Moodle, so readers need to know who made the content and under
which conditions they may reuse it (issue #5). Different resources of a course can have
different authors and licenses. Moodle course content has no reliable license metadata
for pages, books or labels, and the plugin's own GPL license says nothing about the
content.

## Decision

- Attribution is optional teacher configuration in the block instance configuration,
  next to the selection: a default (`authors`, `license`, `customlicense`,
  `licenseurl`) and resource overrides in `cmattribution`, keyed by course module id.
- An override's empty authors and the `inherit` license use the default; an explicit
  empty license means *No license specified* and is not replaced by the default.
- Licenses are the six Creative Commons 4.0 licenses, always linked to their official
  deed, or a custom license with a required name and an optional HTTP(S) URL. A URL is
  kept only for custom licenses, so a stale URL never mislabels another license.
- `publication` normalises settings when saving and again when reading (unknown
  licenses, invalid URLs, control characters and tampered values are dropped).
- Nothing is printed and no license is assumed without configuration.
- Author names are free text that credits the content, not linked to user accounts:
  the privacy provider stays a null provider.

## Alternatives considered

- **A plugin table per resource:** more structure, but a new table, privacy and backup
  code for a handful of strings per course; the block configuration is already backed
  up and restored with the course.
- **Moodle's license manager (`core_license`)** or file license metadata: it describes
  uploaded files, not page, book or label text, and would suggest licenses the teacher
  never chose.
- **Linking authors to Moodle users:** a content author is often an organisation or
  someone outside the site.

## Consequences

- Overrides follow the course modules on restore (`after_restore()` remaps them) and
  are dropped with modules that are not restored.
- The configuration grows by a few strings per overridden resource.
- A resource cannot override the default authors with "no authors": empty means
  inherit.

## Risks

- Teachers may enter an inaccurate license; the plugin prints what was configured.

## Validation

`publication_test` (normalisation, all licenses, invalid URLs, inheritance),
`export_service_test` (visible attribution in page content streams and `/Author` /
`/Subject` metadata of course and resource PDFs), `backup_restore_test` and the Behat
scenarios of the teacher configuration.
