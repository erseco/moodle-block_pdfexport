# Architecture

`block_pdfexport` is a course block with two entry points and one export pipeline.
Decisions behind it are recorded in [adr/](adr/).

## Components

| File | Responsibility |
|---|---|
| `block_pdfexport.php` | Block: lists the published resources the user can export, links to the PDFs and, for managers, to `manage.php`. Renders `templates/content.mustache`. |
| `manage.php` | Controller: login, `block/pdfexport:configure` + `moodle/course:update`, block validation, `selection_form`, save. |
| `download.php` | Controller: login, `block/pdfexport:view`, block validation, `export_service`, `send_file()`. |
| `classes/local/publication.php` | Block configuration: read/write the published selection, the optional attribution (default and per resource, normalised) and the cover stylesheet; validate that a block belongs to a course. |
| `classes/local/cover_css.php` | Strict parser of the cover CSS subset: allowlisted characters, selectors, properties and value grammars; canonical styles and their inline declarations (ADR-0009). |
| `classes/local/resource_detector.php` | Exportable modules of a course, grouped by top-level section in course order (subsections inlined), optionally filtered by access and selection. |
| `classes/local/export_service.php` | The pipeline: available modules → document model → PDF bytes; file names; limits. |
| `classes/local/adapters/*` | One adapter per module type: access check, support level, conversion to parts. |
| `classes/local/adapters/exelearning_adapter.php` | eXeLearning packages: page tree from `#siteNav`, page content from the extracted `content` file area, interactive iDevices replaced (ADR-0007). |
| `classes/local/print_transformer.php` | HTML → TCPDF-safe HTML: allowlist, links, images from the module's own file area, hidden content, media placeholders, warnings. |
| `classes/local/pdf_document.php` | TCPDF (`\pdf`) subclass: cover (optionally styled), header, footer, headings with bookmarks, table of contents. |
| `classes/form/selection_form.php` | Moodle form: one checkbox per exportable module, grouped by section, with toggle-all, an optional attribution override per module, the default attribution and the cover CSS. |
| `classes/privacy/provider.php` | Null privacy provider: no data linked to users (author names are free text). |
| `backup/moodle2/restore_pdfexport_block_task.class.php` | Remaps published course module ids and attribution overrides after a course restore. |

## Request flow

```
download.php?courseid&blockid[&cmid]
  require_login(course) + block/pdfexport:view
  publication::get_course_block()          block belongs to this course?
  export_service(course, selection, attribution, cmattributions, coverstyles)
                                           coverstyles = cover_css::parse(stored CSS), [] if invalid
    get_available_sections()               user's modinfo ∩ selection ∩ adapter::can_access()
    build_course_document() | build_module_document(cmid)
      adapter::export(cm, transformer)     parts: [title, level, html]
        format_html(): file_rewrite_pluginfile_urls + format_text + print_transformer
    render(document)                       pdf_document: metadata, cover, headings, attribution, html, TOC
  send_file(bytes, filename, 'application/pdf')
```

The PDF is fully built before any header is sent: an error shows a Moodle error page,
never a corrupted download.

## Document model

```php
[
    'type' => 'course' | 'module',
    'title' => 'Introduction to Technology',      // plain text, escaped on output
    'subtitle' => 'TECH101',
    'summary' => '<p>…</p>',                       // printable HTML
    'attribution' => ['authors' => ['…'], 'license' => '…', 'licenseurl' => '…'],  // default; [] if none
    'sections' => [
        ['title' => '1. Introduction', 'items' => [
            ['cmid' => 12, 'modname' => 'book', 'title' => '1.2 Introduction book',
             'support' => 'full', 'warnings' => [], 'attribution' => [/* resolved for this cm */],
             'parts' => [['title' => '1. Chapter 1', 'level' => 1, 'html' => '…']]],
        ]],
    ],
    'warnings' => ['1.2 Introduction book: …'],
]
```

Heading levels in the PDF: section (h1, outline 0), resource (h2, 1), chapter (h3, 2),
subchapter (h4, 3). Content headings are shifted to h4–h6 so they never compete with
the structure.

## Configuration

The selection lives in the block instance configuration (`block_instances.configdata`,
serialized by `block_base::instance_config_save()`), as the comma-separated
`selectedcmids` string introduced in 0.3.x. The optional default attribution is
stored in the same configuration (`authors`, one per line; `license`; `customlicense`;
`licenseurl`) and resource overrides in `cmattribution`, keyed by course module id
(remapped on restore). `publication` normalises it on save and on read; see
[ADR-0008](adr/0008-content-attribution.md). Exports never infer a content license.

The optional cover stylesheet is stored as typed in `covercss`. `cover_css::parse()`
validates it in the form and again on every export (stored configuration is not
trusted), producing canonical styles such as `['title' => ['color' => '#123456']]`.
Only the course PDF passes them to `pdf_document::add_cover()`, which appends each
element's declarations to its built-in inline style (TCPDF keeps the last duplicate
declaration) and draws the `.pdfexport-cover` background as a page fill isolated in its
own graphics state. Without styles the cover output is unchanged; see
[ADR-0009](adr/0009-cover-css.md).

## Adding a module type

1. Create `classes/local/adapters/<name>_adapter.php` extending `base_adapter`
   (`get_support_level()`, `export()`, and `get_view_capability()` if the module's
   read capability is not `mod/<name>:view`).
2. Register it in `base_adapter::ADAPTERS`.
3. Add tests to `tests/adapters_test.php` (content, hidden data, access), update the
   support table in README and ADR-0002, and add the language strings.
