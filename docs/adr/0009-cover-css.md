# ADR-0009: Course PDF cover CSS

- **Status:** Accepted
- **Date:** 2026-10-20

## Context

The course PDF cover is drawn by `pdf_document::add_cover()` with hard-coded inline
styles, and teachers want it to match their institution or course (issue #6). TCPDF
6.10.0, bundled with Moodle 5.3, renders only a subset of HTML and CSS: it reads the
inline `style` attribute (the last duplicate declaration wins), `<style>` blocks per
`writeHTML()` call, a few properties (`font-weight` only by its first letter, so numeric
weights are ignored), and has no layout model for margins, positioning, flexbox or grid
on block elements. Teacher input is untrusted even from authorised users: it must not
fetch remote resources, read local files or inject HTML.

## Decision

- An optional **Cover CSS** textarea on the existing management page (same
  capabilities: `block/pdfexport:configure` and `moodle/course:update`), stored as typed
  in the block configuration (`covercss`); a blank field restores the default.
- `cover_css` is an allowlist grammar, not a CSS engine. After removing comments, only
  letters, digits, white space and `# . , : ; { } -` are allowed, which makes quotes,
  backslash escapes, functions (`url()`, `expression()`, `var()`), at-rules,
  `!important` and markup impossible. The input must be a sequence of flat rules; every
  selector, property and value must match the documented lists and grammars, or the
  whole stylesheet is rejected with a localised message. The limit is 8 KiB.
- Supported: the selectors `.pdfexport-cover`, `-title`, `-subtitle`, `-attribution`,
  `-summary` and `-date`; the properties `color`, `background-color` (hex), `font-size`
  (6–72 pt), `font-weight` (normal, bold), `font-style` (normal, italic), `text-align`
  and `line-height` (0.8–3). Each was checked by rendering with the bundled TCPDF.
- Validated styles are compiled into inline declarations appended to each element's
  built-in style, keeping the existing `writeHTML()` calls; `.pdfexport-cover` applies to
  every element and is overridden by the specific selectors. Its `background-color`
  fills the page inside a `q`/`Q` graphics state, and TCPDF's colour state is restored,
  so nothing leaks to the next pages. The summary is wrapped in a styled `div` only when
  it has styles.
- The stored stylesheet is parsed again on every export; an invalid one is ignored (a
  developer debugging message) and the default cover is used.

## Alternatives considered

- **A `<style>` block in a single cover `writeHTML()` call:** the built-in inline styles
  would win over the class rules, so defaults would have to move into the same block,
  and TCPDF's selector matching would become part of the contract.
- **Structured settings (colour pickers, size menus), as in mod_customcert:** safer
  still, but many form fields for a few values, and less familiar for the stable class
  contract that BookStack-style export styling offers.
- **Sanitising arbitrary CSS or HTML (blacklists, HTMLPurifier):** keeps unsupported
  properties that TCPDF silently ignores and depends on catching every bypass.
- **A CSS parsing library:** a new runtime dependency for a grammar of a few lines.

## Consequences

- The default cover is byte-for-byte unchanged when no CSS is configured.
- Teachers get exact error messages instead of styles that silently do nothing.
- Backup and restore keep the stylesheet with the rest of the block configuration.
- Adding a property means checking its TCPDF rendering, extending the grammar, tests and
  the README.

## Risks

- A very large font or line height can push the cover onto a second page; the text is
  kept and the page fill covers only the first cover page.
- Links in the attribution keep TCPDF's link colour.

## Validation

`cover_css_test` (canonical output, limits and rejection of imports, URLs, data URIs,
escapes, expressions, variables, HTML, unsupported selectors, properties and values,
malformed input, size), `publication_test` (persistence, independence, preservation of
the selection and attribution, untrusted stored values, form validation),
`export_service_test` (colours, sizes and page fill in the cover page's content stream;
other pages, outline, metadata and resource PDFs byte-identical to the default),
`backup_restore_test` and the Behat cover scenario.
