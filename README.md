# PDF Export — Moodle 5.3 — v0.3.1-alpha

An experimental course block to select eligible book/page resources and produce PDF using Moodle's built-in TCPDF (pdflib.php). No Chromium/Python/server-level package installation required.

## Update
Install this ZIP over the existing block via the Moodle plugin installer on a TEST instance. Selections are retained as block instance configuration. Keep a backup before updating.

## Supported
- Book chapters/subchapters subject to Moodle access restrictions
- Pages
- Basic printable HTML and simple `<details>/<summary>` accordions
- JPEG/PNG images stored in the relevant Moodle file area, within the size limit
- HTTP(S) embedded video/audio as hyperlinks
- Ordinary text hyperlinks

## Known limitations
- Incompatible CSS and advanced tables may be reformatted.
- Tabs, JS accordions, H5P, SCORM/eXeLearning, remote images, SVG, canvas and arbitrary interactive scripts are not fully supported; some are omitted, with warnings for certain elements. In particular, tabbed or CSS-hidden content needs further adapters; PDF completeness is NOT assured.
- Moodle's PDF library is a limited HTML renderer, not a browser.
- Images must resolve to `pluginfile.php` paths in the same module context and file area.
- Not validated within an actual Moodle 5.3 installation; PHP syntax checks alone are insufficient.

Security: downloader validates course/module permissions, block context and publication selection. Conversion does not make remote requests to fetch media.

## v0.3.1 fix
Permit safe stdClass deserialization in manage.php and download.php, preserving saved resource selection.

## v0.3.2 fix
Use Moodle TCPDF bundled FreeSans font instead of unavailable DejaVuSans. No changes to permissions or instance selection.
