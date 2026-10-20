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

/**
 * Data generator for block_pdfexport.
 *
 * @package    block_pdfexport
 * @category   test
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_pdfexport_generator extends testing_block_generator {
    /**
     * Adds a PDF Export block to a course, publishing the given course modules.
     *
     * @param stdClass $course Course.
     * @param int[] $cmids Published course module ids.
     * @return stdClass Block instance record.
     */
    public function create_course_block(stdClass $course, array $cmids = []): stdClass {
        return $this->create_instance([
            'parentcontextid' => context_course::instance($course->id)->id,
            'pagetypepattern' => 'course-view-*',
            'configdata' => base64_encode(serialize((object)['selectedcmids' => implode(',', $cmids)])),
        ]);
    }
}
