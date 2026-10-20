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

namespace block_pdfexport\local\adapters;

use block_pdfexport\local\print_transformer;

/**
 * Exports an eXeLearning activity (mod_exelearning): the static content of its pages.
 *
 * mod_exelearning extracts the package to its "content" file area, item = revision. The page
 * tree is read from the navigation of index.html (#siteNav), in package order, and each page's
 * content from its HTML file. Only text iDevices are printed: interactive iDevices (games,
 * quizzes, forms...) keep their data and answers in hidden markup, so they are replaced by a
 * placeholder and never passed to the transformer. Images are read from the package files only.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exelearning_adapter extends base_adapter {
    /** @var string[] iDevice types whose markup is static content. */
    private const STATIC_IDEVICES = ['text', 'freetext', 'freetextidevice'];

    /** @var int Maximum number of package pages exported. */
    public const MAX_PAGES = 200;

    /** @var \context_module Context of the activity being exported. */
    private \context_module $context;

    /** @var int Package revision (item id of the content file area). */
    private int $revision = 0;

    /**
     * Returns how much of the module content is reproduced.
     *
     * @return string
     */
    public function get_support_level(): string {
        return self::SUPPORT_PARTIAL;
    }

    /**
     * Converts the description and the static content of every package page.
     *
     * @param \cm_info $cm Course module.
     * @param print_transformer $transformer Transformer.
     * @return array[] Parts.
     */
    public function export(\cm_info $cm, print_transformer $transformer): array {
        global $DB;
        $this->context = $cm->context;
        $this->revision = (int)$DB->get_field('exelearning', 'revision', ['id' => $cm->instance], MUST_EXIST);
        $parts = [['title' => null, 'level' => 0, 'html' => $this->intro_html($cm, $transformer)]];
        $index = $this->get_package_file('index.html');
        if ($index === null) {
            $parts[0]['html'] .= $this->notice(get_string('exportfailed', 'block_pdfexport'), $this->module_url($cm));
            return $parts;
        }
        $pages = array_slice($this->get_pages($index), 0, self::MAX_PAGES);
        foreach ($pages as $page) {
            $file = $this->get_package_file($page['path']);
            $parts[] = [
                'title' => $page['title'],
                'level' => $page['level'],
                'html' => $file === null ? '' : $this->page_html($file->get_content(), $page['path'], $transformer),
            ];
        }
        $parts[] = ['title' => null, 'level' => 0,
            'html' => $this->notice(get_string('exelearningnotice', 'block_pdfexport'), $this->module_url($cm))];
        return $parts;
    }

    /**
     * Reads the page tree from the package navigation.
     *
     * @param \stored_file $index The package index.html.
     * @return array[] Pages in package order: ['title' => string, 'path' => string, 'level' => int].
     */
    private function get_pages(\stored_file $index): array {
        $dom = self::load_html($index->get_content());
        $nav = (new \DOMXPath($dom))->query('//nav[@id="siteNav"]')->item(0);
        if (!$nav) {
            return [['title' => get_string('exelearningpage', 'block_pdfexport'), 'path' => 'index.html', 'level' => 1]];
        }
        $pages = [];
        foreach ((new \DOMXPath($dom))->query('.//a[@href]', $nav) as $link) {
            $path = self::resolve_path('', $link->getAttribute('href'));
            if ($path === null || !str_ends_with($path, '.html')) {
                continue;
            }
            $depth = 0;
            for ($node = $link->parentNode; $node && $node !== $nav; $node = $node->parentNode) {
                $depth += strtolower($node->nodeName) === 'ul' ? 1 : 0;
            }
            $pages[] = ['title' => trim(preg_replace('/\s+/u', ' ', $link->textContent)), 'path' => $path,
                'level' => $depth > 1 ? 2 : 1];
        }
        return $pages;
    }

    /**
     * Extracts the printable content of a package page.
     *
     * @param string $html Page HTML.
     * @param string $path Page path inside the package (for relative URLs).
     * @param print_transformer $transformer Transformer.
     * @return string Printable HTML.
     */
    private function page_html(string $html, string $path, print_transformer $transformer): string {
        $dom = self::load_html($html);
        $xpath = new \DOMXPath($dom);
        $content = $xpath->query('//main//*[contains(concat(" ", normalize-space(@class), " "), " page-content ")]')->item(0)
            ?? $xpath->query('//main')->item(0);
        if (!$content) {
            return '';
        }
        // Theme controls and data kept hidden for scripts (eXeLearning's js-hidden: game data,
        // solutions, feedback) are never content.
        foreach (
            iterator_to_array($xpath->query('.//button | .//*[contains(concat(" ", normalize-space(@class), " "),' .
                ' " js-hidden ")] | .//*[contains(concat(" ", normalize-space(@class), " "), " box-icon ")]', $content)) as $node
        ) {
            $node->parentNode?->removeChild($node);
        }
        foreach (iterator_to_array($xpath->query('.//*[@data-idevice-type]', $content)) as $idevice) {
            $type = strtolower($idevice->getAttribute('data-idevice-type'));
            if (!in_array($type, self::STATIC_IDEVICES, true)) {
                // Human iDevice name from mod_exelearning when available, otherwise the type slug.
                $name = class_exists(\mod_exelearning\local\idevice_types::class) ?
                    \mod_exelearning\local\idevice_types::label($type) : $type;
                $placeholder = $dom->createElement('p');
                $emphasis = $dom->createElement('em');
                $emphasis->appendChild($dom->createTextNode(get_string('exelearninginteractive', 'block_pdfexport', $name)));
                $placeholder->appendChild($emphasis);
                // Keep the iDevice's own title, when it has one inside the replaced markup.
                $title = $xpath->query(
                    './/*[contains(concat(" ", normalize-space(@class), " "), " box-title ")]',
                    $idevice
                )->item(0);
                if ($title) {
                    $idevice->parentNode->insertBefore($title, $idevice);
                }
                $idevice->parentNode->replaceChild($placeholder, $idevice);
                $transformer->add_warning('warning_interactive');
            }
        }
        foreach (iterator_to_array($xpath->query('.//img[@src] | .//a[@href]', $content)) as $element) {
            $attribute = $element->hasAttribute('src') ? 'src' : 'href';
            $url = $this->package_url($path, $element->getAttribute($attribute));
            if ($url !== null) {
                $element->setAttribute($attribute, $url);
            }
        }
        $output = '';
        foreach ($content->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }
        return $transformer->transform($output, $this->context, 'mod_exelearning', 'content', $this->revision);
    }

    /**
     * Turns a URL relative to a package page into the pluginfile URL of the package file.
     *
     * Absolute URLs are returned unchanged (null); pages of the package are not linkable in a
     * PDF, so links to them become plain text later.
     *
     * @param string $pagepath Path of the page that contains the URL.
     * @param string $url URL found in the page.
     * @return string|null Pluginfile URL, or null to leave the URL as it is.
     */
    private function package_url(string $pagepath, string $url): ?string {
        if ($url === '' || preg_match('~^([a-z][a-z0-9+.-]*:|//|#)~i', $url)) {
            return null;
        }
        $path = self::resolve_path(dirname($pagepath) === '.' ? '' : dirname($pagepath), $url);
        if ($path === null || str_ends_with($path, '.html')) {
            return '';
        }
        $dir = dirname($path) === '.' ? '/' : '/' . dirname($path) . '/';
        return \moodle_url::make_pluginfile_url(
            $this->context->id,
            'mod_exelearning',
            'content',
            $this->revision,
            $dir,
            basename($path)
        )->out(false);
    }

    /**
     * Resolves a relative URL against a directory of the package, refusing to leave the package.
     *
     * @param string $dir Directory of the referring file ('' for the root).
     * @param string $url Relative URL (query and fragment are ignored).
     * @return string|null Normalised path without leading slash, or null if invalid.
     */
    private static function resolve_path(string $dir, string $url): ?string {
        $url = rawurldecode((string)parse_url($url, PHP_URL_PATH));
        if ($url === '' || str_starts_with($url, '/')) {
            return null;
        }
        $segments = $dir === '' ? [] : explode('/', $dir);
        foreach (explode('/', $url) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if (!$segments) {
                    return null;
                }
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }
        return $segments ? implode('/', $segments) : null;
    }

    /**
     * Returns a file of the extracted package.
     *
     * @param string $path Path inside the package.
     * @return \stored_file|null
     */
    private function get_package_file(string $path): ?\stored_file {
        $dir = dirname($path) === '.' ? '/' : '/' . dirname($path) . '/';
        $file = get_file_storage()->get_file(
            $this->context->id,
            'mod_exelearning',
            'content',
            $this->revision,
            $dir,
            basename($path)
        );
        return $file ?: null;
    }

    /**
     * Parses a package HTML document without network access.
     *
     * @param string $html HTML.
     * @return \DOMDocument
     */
    private static function load_html(string $html): \DOMDocument {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $dom;
    }
}
