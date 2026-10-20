# ADR-0002: Supported content types

- **Status:** Accepted
- **Date:** 2026-10-20

## Context

A course mixes static content (pages, books, labels, files) with interactive and
personal activities (quizzes, assignments, forums, SCORM, H5P). A static PDF cannot
reproduce interaction, and exporting an activity must never leak attempts, grades,
submissions or posts.

## Decision

Only modules with an adapter are offered for export. Each adapter declares a support
level shown to teachers and students:

| Level | Modules | Meaning |
|---|---|---|
| Full content | page, book, label, url | The content the module stores is reproduced |
| Partial content | resource, folder | Images embedded / files listed and linked |
| Link only | scorm, h5pactivity, exelearning | Description, notice and link |

Every other module type (quiz, assign, forum, wiki, glossary, data, lesson...) is not
listed. Adapters read content only through the module's own records and file areas
(Moodle APIs such as `book_preload_chapters()`), never by scraping the activity page.
`mod_exelearning` is handled when installed, as a reference only: its package is an
interactive site.

Within HTML, presentational hiding (`<details>`, tab panes, collapses) is printed,
while author hiding (`hidden`, `display:none`, `visibility:hidden`, `aria-hidden`) is
omitted with a warning, since it may hold answers or restricted text.

## Alternatives considered

- **Generic page scraping** of `view.php`: would capture theme chrome, JavaScript output
  and per-user data; fragile and unsafe.
- **Exporting quizzes as question lists**: risks disclosing answers and requires
  question-bank permissions; out of scope.
- **Revealing all hidden HTML**: the alpha removed `hidden`/`style`, which could print
  hidden answers; rejected.

## Consequences

Teachers see exactly what each resource yields. New module types need an adapter and
tests (AGENTS.md rule).

## Risks

Content relying on JavaScript to build its text (some filters, embedded apps) prints
incomplete text. Each media/interactive omission is reported in *Conversion warnings*.

## Validation

`adapters_test` covers each adapter; `print_transformer_test` covers hidden content,
`<details>`, tabs, media and interactive elements.
