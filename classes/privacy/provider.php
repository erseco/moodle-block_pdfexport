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

namespace block_pdfexport\privacy;

/**
 * Privacy provider: the block stores no personal data linked to Moodle users.
 *
 * The block configuration holds course module ids and the optional authorship and license
 * of the published content. Author names are free text typed by teachers to credit the
 * content, like the text of a page: they are not linked to any user account, so there is
 * no user data to export or delete. PDFs are generated on request and streamed to the
 * user; nothing is stored or logged by this plugin.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\null_provider {
    /**
     * Returns the language string identifier explaining why no data is stored.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
