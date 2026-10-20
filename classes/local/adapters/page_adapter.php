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
 * Exports a page (mod_page): its content, with images stored in the page.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_adapter extends base_adapter {
    /**
     * Returns how much of the module content is reproduced.
     *
     * @return string
     */
    public function get_support_level(): string {
        return self::SUPPORT_FULL;
    }

    /**
     * Converts the page content.
     *
     * @param \cm_info $cm Course module.
     * @param print_transformer $transformer Transformer.
     * @return array[] Parts.
     */
    public function export(\cm_info $cm, print_transformer $transformer): array {
        global $DB;
        $page = $DB->get_record('page', ['id' => $cm->instance], 'id, content, contentformat', MUST_EXIST);
        // Page files are stored with item id 0; the revision in public URLs is only a cache key.
        $html = $this->format_html(
            $page->content,
            (int)$page->contentformat,
            $cm->context,
            'mod_page',
            'content',
            0,
            $transformer
        );
        return [['title' => null, 'level' => 0, 'html' => $html]];
    }
}
