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
 * Strict parser of the cover CSS: a small, documented subset compiled into inline styles.
 *
 * This is an allowlist grammar, not a CSS engine: after comments are removed, only letters,
 * digits, white space and "# . , : ; { } -" may appear, so quotes, escapes, functions such
 * as url(), at-rules, "!important" and HTML are impossible. Rules must use the documented
 * selectors, properties and value grammars; anything else is rejected, never ignored. The
 * parser consumes the whole input and returns a canonical representation of the styles.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cover_css {
    /** @var int Maximum size of the stylesheet in bytes. */
    public const MAX_LENGTH = 8192;

    /** @var string[] Supported selectors and the cover element each one styles. */
    public const SELECTORS = [
        '.pdfexport-cover' => 'cover',
        '.pdfexport-cover-title' => 'title',
        '.pdfexport-cover-subtitle' => 'subtitle',
        '.pdfexport-cover-attribution' => 'attribution',
        '.pdfexport-cover-summary' => 'summary',
        '.pdfexport-cover-date' => 'date',
    ];

    /** @var array Supported properties: a list of keywords or the name of a value grammar. */
    public const PROPERTIES = [
        'color' => 'colour',
        'background-color' => 'colour',
        'font-size' => 'fontsize',
        'font-weight' => ['normal', 'bold'],
        'font-style' => ['normal', 'italic'],
        'text-align' => ['left', 'center', 'right', 'justify'],
        'line-height' => 'lineheight',
    ];

    /** @var float[] Inclusive font size range, in points. */
    public const FONT_SIZE = [6, 72];

    /** @var float[] Inclusive line height range, as a multiple of the font size. */
    public const LINE_HEIGHT = [0.8, 3];

    /**
     * Parses and validates a cover stylesheet.
     *
     * @param string $css Stylesheet typed by a teacher or read from the block configuration.
     * @return array[] Canonical styles: element => [property => value], in a fixed order.
     * @throws \moodle_exception With a localised message when anything is not supported.
     */
    public static function parse(string $css): array {
        if (strlen($css) > self::MAX_LENGTH) {
            throw new \moodle_exception('covercsstoolong', 'block_pdfexport', '', self::MAX_LENGTH);
        }
        if (!mb_check_encoding($css, 'UTF-8')) {
            throw new \moodle_exception('covercsscharacters', 'block_pdfexport');
        }
        // Comments may hold any text; an unterminated one leaves "/*", which the charset rejects.
        $css = preg_replace('~/\*.*?\*/~s', ' ', $css);
        if (!preg_match('/^[a-zA-Z0-9 \t\r\n#.,:;{}-]*$/', $css)) {
            throw new \moodle_exception('covercsscharacters', 'block_pdfexport');
        }
        // Flat rules only: "selectors { declarations }", nothing outside, no nesting.
        if (!preg_match('/^(?:[^{}]*+\{[^{}]*+\})*+\s*$/', $css)) {
            throw new \moodle_exception('covercsssyntax', 'block_pdfexport');
        }
        preg_match_all('/([^{}]*)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);
        $styles = [];
        foreach ($rules as [, $selectors, $declarations]) {
            $elements = [];
            foreach (explode(',', $selectors) as $selector) {
                $selector = trim($selector);
                if (!isset(self::SELECTORS[$selector])) {
                    throw new \moodle_exception('covercssselector', 'block_pdfexport', '', self::excerpt($selector));
                }
                $elements[] = self::SELECTORS[$selector];
            }
            foreach (explode(';', $declarations) as $declaration) {
                if (trim($declaration) === '') {
                    continue;
                }
                if (!preg_match('/^\s*([a-zA-Z-]+)\s*:(.*)$/s', $declaration, $match)) {
                    throw new \moodle_exception('covercsssyntax', 'block_pdfexport');
                }
                $property = strtolower($match[1]);
                if (!isset(self::PROPERTIES[$property])) {
                    throw new \moodle_exception('covercssproperty', 'block_pdfexport', '', self::excerpt($property));
                }
                $value = self::normalise_value($property, strtolower(trim($match[2])));
                foreach ($elements as $element) {
                    // Later declarations win, as in CSS.
                    $styles[$element][$property] = $value;
                }
            }
        }
        // Canonical order: documented selectors, then documented properties.
        $canonical = [];
        foreach (self::SELECTORS as $element) {
            if (isset($styles[$element])) {
                $canonical[$element] = array_intersect_key(array_merge(self::PROPERTIES, $styles[$element]), $styles[$element]);
            }
        }
        return $canonical;
    }

    /**
     * Returns the inline declarations of one cover element, to append after its built-in style.
     *
     * Declarations of ".pdfexport-cover" apply to every element and the element's own selector
     * overrides them; its background colour fills the cover page instead (see pdf_document).
     *
     * @param array[] $styles Canonical styles from parse().
     * @param string $element Element: title, subtitle, attribution, summary or date.
     * @return string Declarations such as "color:#123456;font-size:30pt;", empty when unstyled.
     */
    public static function declarations(array $styles, string $element): string {
        $cover = $styles['cover'] ?? [];
        unset($cover['background-color']);
        $inline = '';
        foreach (array_merge($cover, $styles[$element] ?? []) as $property => $value) {
            $inline .= $property . ':' . $value . ';';
        }
        return $inline;
    }

    /**
     * Validates a value against the grammar of its property and returns it in canonical form.
     *
     * @param string $property Supported property.
     * @param string $value Lower-case value without surrounding white space.
     * @return string Canonical value.
     * @throws \moodle_exception When the value is not supported.
     */
    private static function normalise_value(string $property, string $value): string {
        $grammar = self::PROPERTIES[$property];
        if (is_array($grammar) && in_array($value, $grammar, true)) {
            return $value;
        }
        if ($grammar === 'colour' && preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $value, $match)) {
            $hex = $match[1];
            return '#' . (strlen($hex) === 3 ? $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2] : $hex);
        }
        if ($grammar === 'fontsize' && preg_match('/^(\d{1,2}(?:\.\d{1,2})?)pt$/', $value, $match)) {
            $size = (float)$match[1];
            if ($size >= self::FONT_SIZE[0] && $size <= self::FONT_SIZE[1]) {
                return $size . 'pt';
            }
        }
        if ($grammar === 'lineheight' && preg_match('/^\d(?:\.\d{1,2})?$/', $value)) {
            $height = (float)$value;
            if ($height >= self::LINE_HEIGHT[0] && $height <= self::LINE_HEIGHT[1]) {
                return (string)$height;
            }
        }
        throw new \moodle_exception(
            'covercssvalue',
            'block_pdfexport',
            '',
            ['property' => $property, 'value' => self::excerpt($value)]
        );
    }

    /**
     * Shortens a fragment of the stylesheet quoted in an error message.
     *
     * @param string $text Fragment, already limited to the allowed characters.
     * @return string
     */
    private static function excerpt(string $text): string {
        return \core_text::substr(preg_replace('/\s+/', ' ', $text), 0, 40);
    }
}
