# ADR-0007: eXeLearning content export

- **Status:** Accepted (amends the eXeLearning row of ADR-0002)
- **Date:** 2026-10-20
- **Issue:** #3

## Context

ADR-0002 exported eXeLearning activities as references only. eXeLearning packages are
mostly static pages of text and images, which a PDF can reproduce, mixed with
interactive iDevices (games, quizzes, forms) whose data and answers are embedded in the
page markup: JSON attributes and elements with the `js-hidden` class, not CSS
`display:none`.

## Decision

`exelearning_adapter` (support level *Partial content*) reads the package that
mod_exelearning extracts to its `content` file area (item = `revision`):

- **Pages**: the order and nesting come from the `#siteNav` navigation of `index.html`,
  and each page is a chapter (subpages are subchapters). If there is no navigation,
  `index.html` is exported as a single page.
- **Content**: the `.page-content` (or `<main>`) of each page. Theme buttons, box icons
  and every `js-hidden` element are removed before conversion.
- **iDevices**: only text iDevices (`data-idevice-type` = `text`) are kept. Every other
  iDevice is replaced by its title and a placeholder naming its type (with the
  mod_exelearning label when it is available), plus a warning. Its markup never reaches
  `print_transformer`.
- **Images and links**: relative URLs are resolved inside the package; paths that would
  leave it are refused. They become pluginfile URLs that `print_transformer` validates
  against the activity's own file area. Links to package pages become plain text, and
  external links are kept.
- **Optional dependency**: mod_exelearning is installed from its repository in
  development (`make exelearning`), CI (`moodle-plugin-ci add-plugin`) and Playground
  (`installMoodlePlugin`). The tests skip themselves when it is missing.
- **CI**: every job installs mod_exelearning, so all PHPUnit and Behat tests, including the
  `@javascript` scenario, run with it. A first workaround limited it to the coverage job
  because the first `@javascript` Behat session hung. The cause was in mod_exelearning: its
  `amd/build/moodle_exe_bridge.min.js` was not an AMD module, so it corrupted Moodle's
  cold-cache RequireJS bundle. It was fixed upstream in exelearning/moodle-mod_exelearning#178.

Reading the exported HTML instead of `content.xml` uses the format eXeLearning publishes
for browsers, and avoids depending on mod_exelearning's internal parser classes.

## Alternatives considered

- **Parse `content.xml` with `\mod_exelearning\local\package`**: an internal API of
  another plugin, and the XML holds iDevice data rather than rendered HTML.
- **Print interactive iDevices with their "solution" text**: would reveal answers.
- **Keep the reference-only export**: loses the static content teachers ask for.

## Consequences

Packages made mostly of text export well. Activities made only of games export as a list
of their titles. New static iDevice types need to be added to `STATIC_IDEVICES` with
tests.

## Risks

eXeLearning export markup can change between versions; the tests use both a real export
and controlled markup. Text iDevices that build content with JavaScript print only their
static HTML.

## Validation

`tests/exelearning_adapter_test.php` (page order and nesting, game data not leaked,
`js-hidden`, traversal, images, fallbacks, permissions, course PDF) and
`tests/behat/pdfexport_exelearning.feature`.
