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

use block_pdfexport\local\adapters\base_adapter;

/**
 * Lists the exportable course modules of a course, grouped by section in course order.
 *
 * Discovery is not authorisation: callers decide whether to keep only the modules the
 * current user can access.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class resource_detector {
    /**
     * Returns the exportable modules grouped by top-level section.
     *
     * Modules inside a subsection (delegated section) are listed in place, under the section
     * that contains the subsection. Sections without matching modules are omitted.
     *
     * @param \course_modinfo $modinfo Modinfo of the user the list is built for.
     * @param bool $onlyaccessible Keep only modules the user can export (see base_adapter::can_access()).
     * @param int[]|null $cmids Keep only these course module ids; null keeps all.
     * @return array[] List of ['number' => int, 'name' => string, 'cms' => \cm_info[]].
     */
    public static function get_sections(\course_modinfo $modinfo, bool $onlyaccessible, ?array $cmids = null): array {
        $filter = $cmids === null ? null : array_fill_keys(array_map('intval', $cmids), true);
        $result = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->is_delegated()) {
                continue;
            }
            $cms = self::section_cms($modinfo, $section, $onlyaccessible, $filter);
            if ($cms) {
                $result[] = [
                    'number' => (int)$section->sectionnum,
                    // Plain text: callers escape it for their output (HTML, Mustache or PDF).
                    'name' => html_entity_decode(get_section_name($modinfo->get_course(), $section), ENT_QUOTES | ENT_HTML5),
                    'cms' => $cms,
                ];
            }
        }
        return $result;
    }

    /**
     * Returns the exportable modules of a section, descending into subsections.
     *
     * @param \course_modinfo $modinfo Modinfo.
     * @param \section_info $section Section.
     * @param bool $onlyaccessible Keep only accessible modules.
     * @param array|null $filter Map of allowed course module ids.
     * @return \cm_info[]
     */
    private static function section_cms(
        \course_modinfo $modinfo,
        \section_info $section,
        bool $onlyaccessible,
        ?array $filter
    ): array {
        $cms = [];
        foreach ($modinfo->sections[$section->sectionnum] ?? [] as $cmid) {
            $cm = $modinfo->get_cm($cmid);
            if ($cm->deletioninprogress) {
                continue;
            }
            $delegated = $cm->get_delegated_section_info();
            if ($delegated) {
                if (!$onlyaccessible || $cm->uservisible) {
                    $cms = array_merge($cms, self::section_cms($modinfo, $delegated, $onlyaccessible, $filter));
                }
                continue;
            }
            $adapter = base_adapter::for_module($cm->modname);
            if (!$adapter || ($filter !== null && !isset($filter[$cm->id]))) {
                continue;
            }
            if ($onlyaccessible && !$adapter->can_access($cm)) {
                continue;
            }
            $cms[] = $cm;
        }
        return $cms;
    }
}
