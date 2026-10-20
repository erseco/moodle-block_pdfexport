<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace block_pdfexport\local;

/**
 * Converts formatted Moodle HTML into the conservative HTML subset that TCPDF renders.
 *
 * The output is built from an allowlist of tags and attributes. Nothing is ever fetched
 * from the network: images are embedded only when they are stored in the file area of the
 * content being exported, and they are passed to TCPDF inline ("@" + base64), so no
 * temporary files are created and TCPDF never resolves a path or URL on its own.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class print_transformer {
    /** @var int Maximum size of a single embedded image, in bytes. */
    public const MAX_IMAGE_BYTES = 10 * 1024 * 1024;

    /** @var int Maximum number of pixels of a single embedded image (decoded by GD in memory). */
    public const MAX_IMAGE_PIXELS = 25000000;

    /** @var int Maximum number of embedded images per export. */
    public const MAX_IMAGES = 300;

    /** @var int Maximum rendered image width, in pixels (fits the A4 text column). */
    public const MAX_IMAGE_WIDTH = 480;

    /** @var string[] Tags kept as they are (attributes are filtered separately). */
    private const ALLOWED = ['a', 'b', 'blockquote', 'br', 'dd', 'del', 'div', 'dl', 'dt', 'em', 'h1', 'h2', 'h3',
        'h4', 'h5', 'h6', 'hr', 'i', 'li', 'ol', 'p', 'pre', 's', 'small', 'span', 'strong', 'sub', 'sup', 'table',
        'td', 'th', 'thead', 'tr', 'u', 'ul'];

    /** @var string[] Tags removed together with their content, silently. */
    private const DROPPED = ['area', 'base', 'head', 'link', 'map', 'meta', 'noscript', 'param', 'script', 'source',
        'style', 'tcpdf', 'template', 'title', 'track'];

    /** @var string[] Interactive or script-driven elements replaced by a placeholder. */
    private const INTERACTIVE = ['button', 'canvas', 'input', 'math', 'select', 'svg', 'textarea'];

    /** @var string[] Embedded media replaced by a link to the original. */
    private const MEDIA = ['audio', 'embed', 'iframe', 'object', 'video'];

    /** @var array<string, string> Block-level structural tags rendered as their closest supported tag. */
    private const RENAMED = ['address' => 'div', 'article' => 'div', 'aside' => 'div', 'center' => 'div',
        'code' => 'tt', 'figcaption' => 'p', 'figure' => 'div', 'footer' => 'div',
        'header' => 'div', 'kbd' => 'tt', 'main' => 'div', 'mark' => 'u', 'nav' => 'div', 'samp' => 'tt',
        'section' => 'div', 'strike' => 's'];

    /** @var array<string, string[]> Attributes kept per tag; everything else is removed. */
    private const ATTRIBUTES = ['a' => ['href'], 'ol' => ['start'], 'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan']];

    /** @var array<string, int> Font size of content headings, in points. */
    private const HEADING_SIZES = ['h4' => 12, 'h5' => 11, 'h6' => 10];

    /** @var string[] Collected warnings (language strings already resolved). */
    private array $warnings = [];

    /** @var int Number of images embedded during the lifetime of this transformer. */
    private int $imagecount = 0;

    /** @var \DOMDocument|null Document being transformed. */
    private ?\DOMDocument $dom = null;

    /** @var array|null File area the current HTML belongs to. */
    private ?array $area = null;

    /**
     * Transforms formatted HTML into printable HTML.
     *
     * @param string $html HTML already processed by format_text() with pluginfile URLs rewritten.
     * @param \context $context Context that owns the file area.
     * @param string $component Component of the file area (for example mod_page).
     * @param string $filearea File area name.
     * @param int|null $itemid Item id, or null when the area URLs have no item id segment.
     * @return string Printable HTML.
     */
    public function transform(
        string $html,
        \context $context,
        string $component,
        string $filearea,
        ?int $itemid
    ): string {
        if (trim($html) === '') {
            return '';
        }
        $this->area = ['contextid' => (int)$context->id, 'component' => $component, 'filearea' => $filearea,
            'itemid' => $itemid];
        $this->dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $this->dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="pdfexport-root">' . $html . '</div>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $this->dom->getElementById('pdfexport-root');
        $this->sanitise_children($root);
        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $this->dom->saveHTML($child);
        }
        $this->dom = null;
        return $output;
    }

    /**
     * Returns an inline image tag for a stored file, or a placeholder when it cannot be embedded.
     *
     * @param \stored_file $file Image file the caller has already authorised.
     * @param string $alt Alternative text.
     * @return string HTML.
     */
    public function image_html(\stored_file $file, string $alt): string {
        $image = $this->load_image($file->get_content(), (int)$file->get_filesize());
        if ($image === null) {
            return $this->placeholder($alt);
        }
        return '<img src="@' . base64_encode($image['data']) . '" width="' . $image['width'] . '" alt="' . s($alt) . '">';
    }

    /**
     * Records a warning for the resource being exported.
     *
     * @param string $identifier Language string identifier in block_pdfexport.
     */
    public function add_warning(string $identifier): void {
        $this->warnings[] = get_string($identifier, 'block_pdfexport');
    }

    /**
     * Returns and clears the warnings collected since the last call.
     *
     * @return string[] Unique warnings.
     */
    public function take_warnings(): array {
        $warnings = array_values(array_unique($this->warnings));
        $this->warnings = [];
        return $warnings;
    }

    /**
     * Normalises a link target, returning null for anything that is not safe to print.
     *
     * Only http, https and mailto are kept. Site-relative links become absolute.
     *
     * @param string $url Raw URL.
     * @return string|null Safe absolute URL.
     */
    public static function safe_url(string $url): ?string {
        global $CFG;
        $url = trim($url);
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $url = $CFG->wwwroot . $url;
        }
        if (preg_match('~^mailto:(.+)$~i', $url, $matches)) {
            return validate_email($matches[1]) ? 'mailto:' . $matches[1] : null;
        }
        if (!preg_match('~^https?://[^\s]+$~i', $url)) {
            return null;
        }
        $clean = clean_param($url, PARAM_URL);
        return $clean === '' ? null : $clean;
    }

    /**
     * Recursively sanitises the children of a node.
     *
     * @param \DOMNode $parent Node whose children are processed.
     */
    private function sanitise_children(\DOMNode $parent): void {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof \DOMText && !($node instanceof \DOMCdataSection)) {
                continue;
            }
            if (!($node instanceof \DOMElement)) {
                $parent->removeChild($node);
                continue;
            }
            $this->sanitise_element($node);
        }
    }

    /**
     * Sanitises one element, replacing or removing it when required.
     *
     * @param \DOMElement $element Element to process.
     */
    private function sanitise_element(\DOMElement $element): void {
        $tag = strtolower($element->tagName);
        if (in_array($tag, self::DROPPED, true)) {
            $element->parentNode->removeChild($element);
            return;
        }
        if ($this->is_hidden($element)) {
            // Decorative aria-hidden elements (icons) are dropped silently. Anything hidden by the
            // author may be a spoiler, an answer or restricted text, so it is never revealed.
            if (strtolower($element->getAttribute('aria-hidden')) !== 'true') {
                $this->add_warning('warning_hiddencontent');
            }
            $element->parentNode->removeChild($element);
            return;
        }
        if (in_array($tag, self::INTERACTIVE, true)) {
            $this->add_warning('warning_interactive');
            $this->replace($element, $this->paragraph(get_string('interactiveplaceholder', 'block_pdfexport')));
            return;
        }
        if (in_array($tag, self::MEDIA, true)) {
            $this->replace_media($element);
            return;
        }
        if ($tag === 'img') {
            $this->replace_image($element);
            return;
        }
        if ($tag === 'details') {
            // Native disclosure widgets are presentational: their content is part of the page.
            $element = $this->rename($element, 'div');
            $tag = 'div';
        } else if ($tag === 'summary' || $tag === 'caption') {
            // Disclosure summaries and table captions become bold paragraphs; a caption is moved
            // before its table because TCPDF does not render captions.
            $paragraph = $this->dom->createElement('p');
            $strong = $this->dom->createElement('strong');
            while ($element->firstChild) {
                $strong->appendChild($element->firstChild);
            }
            $paragraph->appendChild($strong);
            $table = $element->parentNode;
            if ($tag === 'caption' && $table->parentNode) {
                $table->removeChild($element);
                $table->parentNode->insertBefore($paragraph, $table);
            } else {
                $this->replace($element, $paragraph);
            }
            $this->sanitise_children($strong);
            return;
        } else if (isset(self::RENAMED[$tag])) {
            $tag = self::RENAMED[$tag];
            $element = $this->rename($element, $tag);
        } else if (preg_match('/^h([1-6])$/', $tag, $matches)) {
            // Content headings sit below the document structure (h1 section, h2 resource, h3 chapter).
            $tag = 'h' . [1 => 4, 2 => 4, 3 => 5, 4 => 6, 5 => 6, 6 => 6][(int)$matches[1]];
            $element = $this->rename($element, $tag);
        }
        $this->sanitise_children($element);
        if (!in_array($tag, self::ALLOWED, true) && $tag !== 'tt') {
            $this->unwrap($element);
            return;
        }
        $this->filter_attributes($element, $tag);
    }

    /**
     * Keeps only allowed attributes and adds the presentation TCPDF needs.
     *
     * @param \DOMElement $element Element.
     * @param string $tag Lower-case tag name.
     */
    private function filter_attributes(\DOMElement $element, string $tag): void {
        $href = $tag === 'a' ? $element->getAttribute('href') : '';
        foreach (iterator_to_array($element->attributes) as $attribute) {
            if (!in_array(strtolower($attribute->name), self::ATTRIBUTES[$tag] ?? [], true)) {
                $element->removeAttributeNode($attribute);
            }
        }
        foreach (['colspan', 'rowspan', 'start'] as $numeric) {
            if ($element->hasAttribute($numeric)) {
                $element->setAttribute($numeric, (string)max(1, min(1000, (int)$element->getAttribute($numeric))));
            }
        }
        if ($tag === 'a') {
            $safe = self::safe_url($href);
            if ($safe === null) {
                $this->unwrap($element);
                return;
            }
            $element->setAttribute('href', $safe);
        } else if (isset(self::HEADING_SIZES[$tag])) {
            $element->setAttribute('style', 'font-size:' . self::HEADING_SIZES[$tag] . 'pt;');
        } else if ($tag === 'table') {
            $element->setAttribute('border', '1');
            $element->setAttribute('cellpadding', '4');
        }
    }

    /**
     * Tells whether the author hid an element (as opposed to purely presentational hiding).
     *
     * @param \DOMElement $element Element.
     * @return bool
     */
    private function is_hidden(\DOMElement $element): bool {
        if ($element->hasAttribute('hidden') || strtolower($element->getAttribute('aria-hidden')) === 'true') {
            return true;
        }
        $style = strtolower(preg_replace('/\s+/', '', $element->getAttribute('style')));
        return str_contains($style, 'display:none') || str_contains($style, 'visibility:hidden');
    }

    /**
     * Replaces an embedded media element with a descriptive paragraph and, when safe, a link.
     *
     * @param \DOMElement $element Media element.
     */
    private function replace_media(\DOMElement $element): void {
        $src = $element->getAttribute('src') ?: $element->getAttribute('data');
        if ($src === '') {
            foreach ($element->getElementsByTagName('source') as $source) {
                $src = $source->getAttribute('src');
                break;
            }
        }
        $label = trim($element->getAttribute('title')) ?: strtolower($element->tagName);
        $paragraph = $this->paragraph(get_string('mediaplaceholder', 'block_pdfexport', $label) . ' ');
        $url = self::safe_url($src);
        if ($url !== null) {
            $link = $this->dom->createElement('a');
            $link->setAttribute('href', $url);
            $link->appendChild($this->dom->createTextNode($url));
            $paragraph->appendChild($link);
        } else {
            $paragraph->appendChild($this->dom->createTextNode(get_string('mediaunavailable', 'block_pdfexport')));
        }
        $this->add_warning('warning_media');
        $this->replace($element, $paragraph);
    }

    /**
     * Embeds an authorised image inline or replaces it with a placeholder.
     *
     * @param \DOMElement $element Image element.
     */
    private function replace_image(\DOMElement $element): void {
        $src = trim($element->getAttribute('src'));
        $alt = trim($element->getAttribute('alt'));
        $image = null;
        if (preg_match('~^data:image/(png|jpe?g);base64,([a-z0-9+/=\s]+)$~i', $src, $matches)) {
            $data = base64_decode(preg_replace('/\s+/', '', $matches[2]), true);
            $image = $data === false ? null : $this->load_image($data, strlen($data));
        } else if (preg_match('~^https?://~i', $src) && !$this->is_pluginfile_url($src)) {
            $this->add_warning('warning_remoteimage');
        } else {
            $file = $this->resolve_file($src);
            if ($file === null) {
                $this->add_warning('warning_imagenotfound');
            } else {
                $image = $this->load_image($file->get_content(), (int)$file->get_filesize());
            }
        }
        if ($image === null) {
            $this->replace($element, $this->paragraph($this->placeholder($alt)));
            return;
        }
        $img = $this->dom->createElement('img');
        $img->setAttribute('src', '@' . base64_encode($image['data']));
        $img->setAttribute('width', (string)$image['width']);
        $img->setAttribute('alt', $alt);
        $this->replace($element, $img);
    }

    /**
     * Validates image bytes and returns the data with its rendered width.
     *
     * @param string $data Raw image bytes.
     * @param int $size Size in bytes.
     * @return array|null ['data' => string, 'width' => int] or null when it cannot be embedded.
     */
    private function load_image(string $data, int $size): ?array {
        if ($this->imagecount >= self::MAX_IMAGES) {
            $this->add_warning('warning_imagelimit');
            return null;
        }
        if ($size > self::MAX_IMAGE_BYTES) {
            $this->add_warning('warning_imagetoolarge');
            return null;
        }
        // Trust the bytes, not the file name or the declared MIME type.
        $info = @getimagesizefromstring($data);
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            $this->add_warning('warning_imageformat');
            return null;
        }
        if ($info[0] * $info[1] > self::MAX_IMAGE_PIXELS) {
            $this->add_warning('warning_imagetoolarge');
            return null;
        }
        $this->imagecount++;
        return ['data' => $data, 'width' => max(1, min((int)$info[0], self::MAX_IMAGE_WIDTH))];
    }

    /**
     * Tells whether a URL points to this site's pluginfile.php.
     *
     * @param string $url URL.
     * @return bool
     */
    private function is_pluginfile_url(string $url): bool {
        global $CFG;
        return str_starts_with($url, $CFG->wwwroot . '/pluginfile.php');
    }

    /**
     * Resolves a pluginfile.php URL to a file of the file area being transformed.
     *
     * Files of any other context, component, area or item are rejected, as are paths with
     * empty, "." or ".." segments.
     *
     * @param string $src Image source.
     * @return \stored_file|null
     */
    private function resolve_file(string $src): ?\stored_file {
        if (!$this->is_pluginfile_url($src)) {
            return null;
        }
        global $CFG;
        $rest = substr($src, strlen($CFG->wwwroot . '/pluginfile.php'));
        if (str_starts_with($rest, '?')) {
            parse_str((string)parse_url($rest, PHP_URL_QUERY), $query);
            $path = is_string($query['file'] ?? null) ? $query['file'] : '';
        } else {
            $path = rawurldecode((string)parse_url($rest, PHP_URL_PATH));
        }
        $segments = explode('/', ltrim($path, '/'));
        $expected = [(string)$this->area['contextid'], $this->area['component'], $this->area['filearea']];
        if ($this->area['itemid'] !== null) {
            $expected[] = (string)$this->area['itemid'];
        }
        if (array_slice($segments, 0, count($expected)) !== $expected) {
            return null;
        }
        $segments = array_slice($segments, count($expected));
        if (!$segments || array_intersect($segments, ['', '.', '..'])) {
            return null;
        }
        $filename = array_pop($segments);
        $filepath = $segments ? '/' . implode('/', $segments) . '/' : '/';
        $file = get_file_storage()->get_file(
            $this->area['contextid'],
            $this->area['component'],
            $this->area['filearea'],
            $this->area['itemid'] ?? 0,
            $filepath,
            $filename
        );
        return $file && !$file->is_directory() ? $file : null;
    }

    /**
     * Returns the placeholder text for an image that was not embedded.
     *
     * @param string $alt Alternative text.
     * @return string Plain text.
     */
    private function placeholder(string $alt): string {
        $alt = $alt !== '' ? $alt : get_string('imagenodescription', 'block_pdfexport');
        return get_string('imagenotincluded', 'block_pdfexport', $alt);
    }

    /**
     * Creates an emphasised paragraph with text.
     *
     * @param string $text Plain text.
     * @return \DOMElement
     */
    private function paragraph(string $text): \DOMElement {
        $paragraph = $this->dom->createElement('p');
        $emphasis = $this->dom->createElement('em');
        $emphasis->appendChild($this->dom->createTextNode($text));
        $paragraph->appendChild($emphasis);
        return $paragraph;
    }

    /**
     * Replaces a node with another one.
     *
     * @param \DOMNode $old Node to replace.
     * @param \DOMNode $new Replacement.
     */
    private function replace(\DOMNode $old, \DOMNode $new): void {
        $old->parentNode->replaceChild($new, $old);
    }

    /**
     * Renames an element by moving its children and attributes to a new one.
     *
     * @param \DOMElement $element Element.
     * @param string $tag New tag name.
     * @return \DOMElement The new element.
     */
    private function rename(\DOMElement $element, string $tag): \DOMElement {
        $new = $this->dom->createElement($tag);
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $new->setAttribute($attribute->name, $attribute->value);
        }
        while ($element->firstChild) {
            $new->appendChild($element->firstChild);
        }
        $this->replace($element, $new);
        return $new;
    }

    /**
     * Replaces an element with its children.
     *
     * @param \DOMElement $element Element.
     */
    private function unwrap(\DOMElement $element): void {
        $parent = $element->parentNode;
        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }
}
