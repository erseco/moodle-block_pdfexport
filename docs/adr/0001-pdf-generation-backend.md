# ADR-0001: PDF generation backend

- **Status:** Accepted
- **Date:** 2026-10-20

## Context

The block must turn Moodle content into PDF on ordinary Moodle hosting. The 0.3.x alpha
already used Moodle's bundled TCPDF (`lib/pdflib.php`, TCPDF 6.10 in Moodle 5.3) for
pages and books. Many Moodle sites cannot install system packages or run browsers.

## Decision

Keep Moodle's `pdf` class (TCPDF) as the only runtime backend. `pdf_document` extends it
to add the cover, running header, page numbers, outline bookmarks and table of contents
(`addTOC()`), with the bundled `freesans` Unicode font. Content is converted to the HTML
subset TCPDF's `writeHTML()` handles (see ADR-0004 and `print_transformer`). Images are
passed inline as `@` + base64, so TCPDF never resolves a path or URL itself.

## Alternatives considered

- **Headless Chromium / Playwright / wkhtmltopdf**: faithful rendering of modern
  HTML/CSS and JavaScript, but needs a browser binary on the server, more memory and a
  sandbox; unavailable on most shared hosting.
- **External conversion service**: sends course content (possibly restricted) to a
  third party, needs credentials and network access; rejected for privacy.
- **LibreOffice / Pandoc**: system packages and process spawning; same deployment issue.
- **mPDF or Dompdf via Composer**: better CSS support than TCPDF, but a new
  third-party dependency (`thirdpartylibs.xml`, security updates) for a gain that does
  not cover JavaScript content either.

## Consequences

- Zero extra runtime dependencies; works wherever Moodle works.
- Rendering is limited to what TCPDF supports: no floats, positioning or most CSS;
  output is simplified but predictable.
- Existing PDF files cannot be merged into the course PDF (TCPDF has no import);
  they are linked instead. A merge would need FPDI: a new dependency that requires a
  new ADR.

## Risks

- TCPDF quirks with unusual HTML. Mitigated by the allowlist transformer, which only
  emits tags TCPDF supports.
- Large documents use memory in the PHP process (see ADR-0004 measurements).

## Validation

PHPUnit renders course and module PDFs and checks the PDF signature, metadata,
outline titles and page count (`export_service_test`). Behat downloads real PDFs.
