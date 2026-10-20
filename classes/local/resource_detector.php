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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.
namespace block_pdfexport\local;

defined('MOODLE_INTERNAL') || die();

final class resource_detector {
    /**
     * Candidate content modules, including unresolved eXeLearning packages.
     * Discovery is not an authorization to access their content.
     */
    public static function get_resources(int $courseid): array {
        $resources = [];
        $modinfo = get_fast_modinfo($courseid);
        foreach ($modinfo->get_cms() as $cm) {
            if (!in_array($cm->modname, ['book', 'page', 'scorm', 'resource'], true)) {
                continue;
            }
            $supported = in_array($cm->modname, ['book', 'page'], true);
            $resources[] = [
                'cmid' => (int)$cm->id,
                'name' => $cm->name,
                'modname' => $cm->modname,
                'section' => (int)$cm->sectionnum,
                'supported' => $supported,
                'uservisible' => (bool)$cm->uservisible,
            ];
        }
        return $resources;
    }
}
