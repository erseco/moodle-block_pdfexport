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
 * Base class of the per-module content adapters.
 *
 * An adapter turns one course module into printable parts. It is only called for modules the
 * current user can access (see {@see self::can_access()}), and it must only read content through
 * the module's own records and file areas.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_adapter {
    /** @var string The module content is reproduced. */
    public const SUPPORT_FULL = 'full';

    /** @var string Part of the content is reproduced; the rest is linked. */
    public const SUPPORT_PARTIAL = 'partial';

    /** @var string Only a description and a link are included. */
    public const SUPPORT_REFERENCE = 'reference';

    /** @var array<string, class-string<base_adapter>> Adapter class per module name. */
    private const ADAPTERS = [
        'page' => page_adapter::class,
        'book' => book_adapter::class,
        'label' => label_adapter::class,
        'resource' => resource_adapter::class,
        'folder' => folder_adapter::class,
        'url' => url_adapter::class,
        'scorm' => reference_adapter::class,
        'h5pactivity' => reference_adapter::class,
        'exelearning' => reference_adapter::class,
    ];

    /**
     * Returns the adapter for a module type.
     *
     * @param string $modname Module name, for example "page".
     * @return base_adapter|null Null when the module type is not exportable.
     */
    public static function for_module(string $modname): ?base_adapter {
        $class = self::ADAPTERS[$modname] ?? null;
        return $class ? new $class() : null;
    }

    /**
     * Returns the module types that have an adapter.
     *
     * @return string[]
     */
    public static function get_module_names(): array {
        return array_keys(self::ADAPTERS);
    }

    /**
     * Returns how much of the module content is reproduced.
     *
     * @return string One of the SUPPORT_* constants.
     */
    abstract public function get_support_level(): string;

    /**
     * Converts a module into printable parts.
     *
     * Each part is ['title' => ?string, 'level' => int, 'html' => string]. Level 0 is the body of
     * the module itself; 1 and 2 are chapters and subchapters.
     *
     * @param \cm_info $cm Course module, already authorised by {@see self::can_access()}.
     * @param print_transformer $transformer Transformer shared by the whole export.
     * @return array[] Parts in reading order.
     */
    abstract public function export(\cm_info $cm, print_transformer $transformer): array;

    /**
     * Tells whether the current user may export the module.
     *
     * The module and its section must be visible and available to the user (cm_info::$uservisible
     * covers hidden modules, availability conditions and group restrictions) and, when the module
     * defines one, the user needs its view capability.
     *
     * @param \cm_info $cm Course module obtained from the current user's modinfo.
     * @return bool
     */
    public function can_access(\cm_info $cm): bool {
        // The section is checked as well: a hidden or restricted section hides its modules even if
        // their own flags were left visible (for example by a direct database change or a restore).
        if (!$cm->uservisible || $cm->deletioninprogress || !$cm->get_section_info()->uservisible) {
            return false;
        }
        $capability = $this->get_view_capability($cm->modname);
        return $capability === null || has_capability($capability, $cm->context);
    }

    /**
     * Returns the capability needed to read the module content.
     *
     * @param string $modname Module name.
     * @return string|null Null when the module defines no view capability.
     */
    protected function get_view_capability(string $modname): ?string {
        $capability = 'mod/' . $modname . ':view';
        return get_capability_info($capability) ? $capability : null;
    }

    /**
     * Formats stored module text and converts it into printable HTML.
     *
     * @param string $text Raw text with @@PLUGINFILE@@ placeholders.
     * @param int $format Text format.
     * @param \context $context Context owning the file area.
     * @param string $component File area component.
     * @param string $filearea File area name.
     * @param int|null $itemid Item id, or null when the area URLs have no item id.
     * @param print_transformer $transformer Transformer.
     * @return string Printable HTML.
     */
    protected function format_html(
        string $text,
        int $format,
        \context $context,
        string $component,
        string $filearea,
        ?int $itemid,
        print_transformer $transformer
    ): string {
        $text = file_rewrite_pluginfile_urls($text, 'pluginfile.php', $context->id, $component, $filearea, $itemid);
        $text = format_text($text, $format, ['context' => $context, 'overflowdiv' => false]);
        return $transformer->transform($text, $context, $component, $filearea, $itemid);
    }

    /**
     * Formats the module description (intro), if the module has one.
     *
     * @param \cm_info $cm Course module.
     * @param print_transformer $transformer Transformer.
     * @return string Printable HTML, empty when there is no description.
     */
    protected function intro_html(\cm_info $cm, print_transformer $transformer): string {
        global $DB;
        $instance = $DB->get_record($cm->modname, ['id' => $cm->instance], 'id, intro, introformat');
        if (!$instance || trim((string)$instance->intro) === '') {
            return '';
        }
        return $this->format_html(
            $instance->intro,
            (int)$instance->introformat,
            $cm->context,
            'mod_' . $cm->modname,
            'intro',
            null,
            $transformer
        );
    }

    /**
     * Builds a highlighted notice, optionally followed by a safe link.
     *
     * @param string $text Plain text.
     * @param string|null $url Absolute URL to include.
     * @return string HTML.
     */
    protected function notice(string $text, ?string $url = null): string {
        $html = s($text);
        $url = $url === null ? null : print_transformer::safe_url($url);
        if ($url !== null) {
            $html .= ' <a href="' . s($url) . '">' . s($url) . '</a>';
        }
        return '<table cellpadding="6"><tr><td style="background-color:#fff4d6;color:#5c4400;">' .
            $html . '</td></tr></table>';
    }

    /**
     * Returns the absolute URL of the module page.
     *
     * @param \cm_info $cm Course module.
     * @return string|null
     */
    protected function module_url(\cm_info $cm): ?string {
        return $cm->url ? $cm->url->out(false) : null;
    }
}
