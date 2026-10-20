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

namespace block_pdfexport;

use block_pdfexport\local\publication;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/blocks/pdfexport/backup/moodle2/restore_pdfexport_block_task.class.php');

/**
 * Tests that course backup and restore keep the published selection.
 *
 * @package    block_pdfexport
 * @category   test
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\restore_pdfexport_block_task::class)]
final class backup_restore_test extends \advanced_testcase {
    /**
     * The selected course modules are remapped to the restored ones; unknown ids are dropped.
     */
    public function test_restore_remaps_selection(): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->enableavailability = true;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id, 'name' => 'Restored page']);
        $book = $generator->create_module('book', ['course' => $course->id, 'name' => 'Restored book']);
        $generator->get_plugin_generator('block_pdfexport')->create_course_block(
            $course,
            [(int)$book->cmid, (int)$page->cmid, 999999]
        );

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $bc->execute_plan();
        $backupid = $bc->get_backupid();
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course('Copy', 'COPY', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $record = $DB->get_record('block_instances', ['blockname' => 'pdfexport',
            'parentcontextid' => \context_course::instance($newcourseid)->id], '*', MUST_EXIST);
        $block = publication::get_course_block($record->id, $newcourseid);
        $modinfo = get_fast_modinfo($newcourseid);
        $names = array_map(
            fn(int $cmid): string => $modinfo->get_cm($cmid)->name,
            publication::get_selected_cmids($block->config)
        );
        $this->assertSame(['Restored book', 'Restored page'], $names);
    }
}
