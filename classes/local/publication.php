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

/**
 * Reads and writes the published selection stored in the block instance configuration.
 *
 * The selection is the comma-separated "selectedcmids" property of the block config, the
 * format used since the first alpha release, so existing instances keep their selection.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class publication {
    /**
     * Returns the published course module ids of a block configuration.
     *
     * @param mixed $config Block config (block_base::$config), possibly null or tampered with.
     * @return int[] Positive unique ids.
     */
    public static function get_selected_cmids($config): array {
        if (!is_object($config) || !isset($config->selectedcmids) || !is_scalar($config->selectedcmids)) {
            return [];
        }
        $ids = array_map('intval', explode(',', (string)$config->selectedcmids));
        return array_values(array_unique(array_filter($ids, fn(int $id): bool => $id > 0)));
    }

    /**
     * Stores a new selection, keeping any other configuration properties.
     *
     * @param \block_base $block Block instance.
     * @param int[] $cmids Course module ids, already validated against the course.
     */
    public static function save(\block_base $block, array $cmids): void {
        $config = is_object($block->config) ? clone $block->config : new \stdClass();
        $config->selectedcmids = implode(',', self::get_selected_cmids((object)['selectedcmids' => implode(',', $cmids)]));
        $block->instance_config_save($config);
        $block->config = $config;
    }

    /**
     * Loads and validates a PDF Export block instance of a course.
     *
     * Request parameters are never trusted: the block must exist, be a PDF Export block and
     * live in the context of the given course (not in another course, the front page or a
     * user dashboard).
     *
     * @param int $blockid Block instance id.
     * @param int $courseid Course id.
     * @return \block_base Block instance with its configuration loaded.
     * @throws \moodle_exception When the block does not belong to the course.
     */
    public static function get_course_block(int $blockid, int $courseid): \block_base {
        global $DB;
        $record = $DB->get_record('block_instances', ['id' => $blockid, 'blockname' => 'pdfexport']);
        $coursecontext = \context_course::instance($courseid, IGNORE_MISSING);
        if (
            !$record || !$coursecontext || $courseid == SITEID ||
                (int)$record->parentcontextid !== (int)$coursecontext->id
        ) {
            throw new \moodle_exception('invalidblock', 'block_pdfexport');
        }
        return block_instance('pdfexport', $record);
    }
}
