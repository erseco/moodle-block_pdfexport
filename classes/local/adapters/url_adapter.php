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
 * Exports a URL (mod_url): its description and the external address.
 *
 * Only the stored address is printed. URL parameters configured in the activity are never
 * appended, because they can contain user data (user id, name, email...).
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class url_adapter extends base_adapter {
    /**
     * Returns how much of the module content is reproduced.
     *
     * @return string
     */
    public function get_support_level(): string {
        return self::SUPPORT_FULL;
    }

    /**
     * Converts the description and the address.
     *
     * @param \cm_info $cm Course module.
     * @param print_transformer $transformer Transformer.
     * @return array[] Parts.
     */
    public function export(\cm_info $cm, print_transformer $transformer): array {
        global $DB;
        $html = $this->intro_html($cm, $transformer);
        $address = (string)$DB->get_field('url', 'externalurl', ['id' => $cm->instance], MUST_EXIST);
        $url = print_transformer::safe_url($address);
        if ($url !== null) {
            $html .= '<p><a href="' . s($url) . '">' . s($url) . '</a></p>';
        } else {
            $html .= '<p>' . s($address) . '</p>';
        }
        return [['title' => null, 'level' => 0, 'html' => $html]];
    }
}
