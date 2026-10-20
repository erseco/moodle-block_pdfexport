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
 * Exports a file (mod_resource): PNG and JPEG images are embedded; other files are linked.
 *
 * Existing PDF files are not merged: Moodle's TCPDF cannot import PDF pages (see ADR-0001).
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resource_adapter extends base_adapter {
    /**
     * Returns how much of the module content is reproduced.
     *
     * @return string
     */
    public function get_support_level(): string {
        return self::SUPPORT_PARTIAL;
    }

    /**
     * Converts the description and the main file.
     *
     * @param \cm_info $cm Course module.
     * @param print_transformer $transformer Transformer.
     * @return array[] Parts.
     */
    public function export(\cm_info $cm, print_transformer $transformer): array {
        $html = $this->intro_html($cm, $transformer);
        // Same main-file rule as mod_resource: highest sort order first.
        $files = get_file_storage()->get_area_files(
            $cm->context->id,
            'mod_resource',
            'content',
            0,
            'sortorder DESC, id ASC',
            false
        );
        $file = reset($files);
        if (!$file) {
            $html .= $this->notice(get_string('exportfailed', 'block_pdfexport'), $this->module_url($cm));
        } else if (in_array($file->get_mimetype(), ['image/png', 'image/jpeg'], true)) {
            $html .= '<p>' . $transformer->image_html($file, $cm->name) . '</p>';
        } else {
            $url = \moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                'mod_resource',
                'content',
                0,
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
            $html .= '<p><strong>' . s($file->get_filename()) . '</strong> (' . display_size($file->get_filesize()) .
                ')</p>';
            $html .= $this->notice(get_string('filenotembedded', 'block_pdfexport'), $url);
        }
        return [['title' => null, 'level' => 0, 'html' => $html]];
    }
}
