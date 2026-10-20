# Architecture

`block_pdfexport` is a course block with two entry points and one export pipeline.
Decisions behind it are recorded in [adr/](adr/).

## Components

| File | Responsibility |
|---|---|
| `block_pdfexport.php` | Block: lists the published resources the user can export, links to the PDFs and, for managers, to `manage.php`. Renders `templates/content.mustache`. |
| `manage.php` | Controller: login, `block/pdfexport:configure` + `moodle/course:update`, block validation, `selection_form`, save. |
| `download.php` | Controller: login, `block/pdfexport:view`, block validation, `export_service`, `send_file()`. |
| `classes/local/publication.php` | Block configuration: read/write `selectedcmids` (alpha-compatible), validate that a block belongs to a course. |
| `classes/local/resource_detector.php` | Exportable modules of a course, grouped by top-level section in course order (subsections inlined), optionally filtered by access and selection. |
| `classes/local/export_service.php` | The pipeline: available modules → document model → PDF bytes; file names; limits. |
| `classes/local/adapters/*` | One adapter per module type: access check, support level, conversion to parts. |
| `classes/local/print_transformer.php` | HTML → TCPDF-safe HTML: allowlist, links, images from the module's own file area, hidden content, media placeholders, warnings. |
| `classes/local/pdf_document.php` | TCPDF (`\pdf`) subclass: cover, header, footer, headings with bookmarks, table of contents. |
| `classes/form/selection_form.php` | Moodle form: one checkbox per exportable module, grouped by section, with toggle-all. |
| `classes/privacy/provider.php` | Null privacy provider. |
| `backup/moodle2/restore_pdfexport_block_task.class.php` | Remaps published course module ids after a course restore. |

## Request flow

```
download.php?courseid&blockid[&cmid]
  require_login(course) + block/pdfexport:view
  publication::get_course_block()          block belongs to this course?
  export_service(course, selection)
    get_available_sections()               user's modinfo ∩ selection ∩ adapter::can_access()
    build_course_document() | build_module_document(cmid)
      adapter::export(cm, transformer)     parts: [title, level, html]
        format_html(): file_rewrite_pluginfile_urls + format_text + print_transformer
    render(document)                       pdf_document: cover, headings, html, warnings, TOC
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
    'sections' => [
        ['title' => '1. Introduction', 'items' => [
            ['cmid' => 12, 'modname' => 'book', 'title' => '1.2 Introduction book',
             'support' => 'full', 'warnings' => [],
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
`selectedcmids` string introduced in 0.3.x. No database tables are used.

## Adding a module type

1. Create `classes/local/adapters/<name>_adapter.php` extending `base_adapter`
   (`get_support_level()`, `export()`, and `get_view_capability()` if the module's
   read capability is not `mod/<name>:view`).
2. Register it in `base_adapter::ADAPTERS`.
3. Add tests to `tests/adapters_test.php` (content, hidden data, access), update the
   support table in README and ADR-0002, and add the language strings.
