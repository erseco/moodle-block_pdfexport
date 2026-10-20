# ADR-0004: Export architecture

- **Status:** Accepted
- **Date:** 2026-10-20

## Context

The alpha generated PDFs inside `download.php`, one module at a time. A course export
needs the same conversion for many modules, ordering, a table of contents and warnings,
without duplicating the single-resource path.

## Decision

One pipeline, `classes/local/export_service.php`, serves both modes:

```
download.php ─► publication (block + selection) ─► export_service
                                                     ├─ resource_detector (sections, cms in course order)
                                                     ├─ adapters/* (cm ─► parts)  ─► print_transformer (HTML)
                                                     └─ render(document) ─► pdf_document (TCPDF)
```

`build_course_document()` / `build_module_document()` produce a plain array model
(sections → items → parts, plus warnings); `render()` turns any model into PDF bytes.
Adapters are small classes behind `base_adapter::for_module()`; a failure in one module
becomes a warning instead of aborting the export.

Exports run synchronously. Measured on Moodle 5.3 / PHP 8.4 (Docker, Apple M-series):
300 pages, each with a 1200×800 JPEG and about 4 KB of text, take 1.0 s to collect and
4.4 s to render, 6.1 MB of output and 114 MB peak memory. `download.php` raises the
time limit to 300 s and memory to `MEMORY_HUGE`. Limits: 300 resources per PDF, 300
images, 10 MB / 25 megapixels per image, 500 files listed per folder.

## Alternatives considered

- **Adhoc task + stored file + notification**: needed only if realistic courses time
  out; the measurements do not justify the extra storage, cleanup and privacy surface.
- **An interface per adapter role / a separate collector class**: one abstract base
  with three helpers covers all adapters; more types would add indirection only.

## Consequences

Adding a module type means one adapter class, one registry line and tests. The model
is testable without parsing PDFs.

## Risks

Very large courses with huge PNGs (decoded by GD) may approach memory limits; the
resource and image limits bound this. Revisit asynchronous export if real courses hit
the limits.

## Validation

`export_service_test` and `adapters_test`; the performance figure above can be
reproduced with a generated course (see DEVELOPMENT.md).
