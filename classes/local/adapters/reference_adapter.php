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
 * Describes interactive activities (SCORM, H5P) that cannot be printed.
 *
 * Only the description and a link to the activity are exported: never attempts, grades,
 * answers or tracking data.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reference_adapter extends base_adapter {
    /**
     * Returns how much of the module content is reproduced.
     *
     * @return string
     */
    public function get_support_level(): string {
        return self::SUPPORT_REFERENCE;
    }

    /**
     * Builds the description and the unsupported-content notice.
     *
     * @param \cm_info $cm Course module.
     * @param print_transformer $transformer Transformer.
     * @return array[] Parts.
     */
    public function export(\cm_info $cm, print_transformer $transformer): array {
        $html = $this->intro_html($cm, $transformer);
        $html .= $this->notice(get_string('referenceonly', 'block_pdfexport') . ' ' .
            get_string('openinmoodle', 'block_pdfexport'), $this->module_url($cm));
        $transformer->add_warning('referenceonly');
        return [['title' => null, 'level' => 0, 'html' => $html]];
    }
}
