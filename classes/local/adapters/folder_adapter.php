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
 * Exports a folder (mod_folder) as a listing of links to its files.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class folder_adapter extends base_adapter {
    /** @var int Maximum number of files listed. */
    public const MAX_FILES = 500;

    /**
     * Returns how much of the module content is reproduced.
     *
     * @return string
     */
    public function get_support_level(): string {
        return self::SUPPORT_PARTIAL;
    }

    /**
     * Converts the description and the file listing.
     *
     * @param \cm_info $cm Course module.
     * @param print_transformer $transformer Transformer.
     * @return array[] Parts.
     */
    public function export(\cm_info $cm, print_transformer $transformer): array {
        $html = $this->intro_html($cm, $transformer);
        $files = get_file_storage()->get_area_files(
            $cm->context->id,
            'mod_folder',
            'content',
            0,
            'filepath, filename',
            false
        );
        if (!$files) {
            $html .= $this->notice(get_string('emptyfolder', 'block_pdfexport'));
            return [['title' => null, 'level' => 0, 'html' => $html]];
        }
        $items = '';
        foreach (array_slice($files, 0, self::MAX_FILES) as $file) {
            $name = ltrim($file->get_filepath(), '/') . $file->get_filename();
            $url = \moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                'mod_folder',
                'content',
                0,
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
            $items .= '<li><a href="' . s($url) . '">' . s($name) . '</a> (' . display_size($file->get_filesize()) .
                ')</li>';
        }
        $html .= '<ul>' . $items . '</ul>';
        if (count($files) > self::MAX_FILES) {
            $html .= '<p>' . s(get_string('foldertruncated', 'block_pdfexport', count($files) - self::MAX_FILES)) .
                '</p>';
        }
        $html .= $this->notice(get_string('foldernotembedded', 'block_pdfexport'), $this->module_url($cm));
        return [['title' => null, 'level' => 0, 'html' => $html]];
    }
}
