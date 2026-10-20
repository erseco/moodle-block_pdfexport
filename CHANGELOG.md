# Changelog

All notable changes to this plugin are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and versions follow
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- eXeLearning activities (mod_exelearning) are exported page by page: page tree from the
  package navigation, text iDevices printed with their images, interactive iDevices named
  and omitted without leaking their data (#3).
- Course PDF: every published resource in course order, grouped by section, with a
  cover, table of contents, bookmarks, running header, page numbers and a conversion
  warnings page.
- Adapters for text and media areas, files (PNG/JPEG embedded, other files linked),
  folders (file listing) and URLs; SCORM, H5P and eXeLearning activities exported as a
  description with a link.
- Selection page grouped by section, with support level per resource and "Select all".
- Restore support: the published selection follows the restored course modules.
- Null privacy provider.
- PHPUnit and Behat suites, 90% coverage gate, GitHub Actions CI, release packaging,
  Dependabot, Moodle Playground blueprint and PR previews, documentation and ADRs.
- Spanish and English strings for all new texts.

### Changed
- One export pipeline (`export_service`) for course and resource PDFs.
- HTML conversion rebuilt on an allowlist: content headings demoted below the document
  structure, tables bordered, captions kept, `<details>` expanded, tab panes printed.
- Images are embedded inline from the resource's own file area: no temporary files.
- Downloads re-check permissions for the current user every time, including section
  visibility, availability, groups, module capabilities and course access.
- Configuration saved through `block_base::instance_config_save()`; the 0.3.x
  `selectedcmids` format is kept, so existing selections survive the upgrade.
- Download file names: `<course short name>[-<resource>].pdf`.

### Fixed
- Content hidden by its author (`hidden`, `display:none`) is no longer revealed.
- Images now render on web downloads (the alpha passed local paths that TCPDF
  resolved against the web server document root).
- `mailto:` links and site-relative links are kept; unsafe schemes are removed.

### Security
- Block ids are validated against the course context; foreign course modules and
  unknown ids get the same error as unpublished ones.

## [0.3.2-alpha] - 2026-10-09

Initial plugin by HenarLG.

### Added
- PDF Export block listing teacher-selected pages, books, SCORM and file resources.
- PDF download of pages and books with Moodle's TCPDF (FreeSans font).
- Basic HTML handling: `<details>`, media links, PNG/JPEG images from the module files.
