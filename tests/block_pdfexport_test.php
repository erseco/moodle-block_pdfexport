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

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once($CFG->dirroot . '/blocks/pdfexport/block_pdfexport.php');

/**
 * Tests for the block content, per role, and the plugin lifecycle.
 *
 * @package    block_pdfexport
 * @category   test
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_pdfexport::class)]
#[CoversClass(privacy\provider::class)]
final class block_pdfexport_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Block instance record. */
    private \stdClass $record;

    /** @var \stdClass[] Modules by key. */
    private array $cms = [];

    /**
     * Creates a course with published modules and a block.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        foreach (['page' => 'page', 'scorm' => 'h5pactivity', 'label' => 'label', 'hidden' => 'page'] as $key => $modname) {
            $this->cms[$key] = $generator->create_module($modname, ['course' => $this->course->id,
                'name' => 'Item ' . $key, 'visible' => $key === 'hidden' ? 0 : 1]);
        }
        $this->record = $generator->get_plugin_generator('block_pdfexport')->create_course_block(
            $this->course,
            array_map(fn($cm) => (int)$cm->cmid, $this->cms)
        );
    }

    /**
     * Returns the block content for the current user.
     *
     * @param int|null $courseid Course of the page (defaults to the test course).
     * @return \stdClass
     */
    private function content(?int $courseid = null): \stdClass {
        $page = new \moodle_page();
        $page->set_course(get_course($courseid ?? $this->course->id));
        $block = block_instance('pdfexport', $this->record, $page);
        return $block->get_content();
    }

    /**
     * Students see the published, visible resources and download links, but no management link.
     */
    public function test_student_content(): void {
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'student'));
        $content = $this->content();
        $this->assertStringContainsString(get_string('downloadcoursepdf', 'block_pdfexport'), $content->text);
        $this->assertStringContainsString('Item page', $content->text);
        $this->assertStringContainsString('cmid=' . $this->cms['page']->cmid, $content->text);
        $this->assertStringContainsString('Item scorm', $content->text);
        $this->assertStringNotContainsString('cmid=' . $this->cms['scorm']->cmid, $content->text);
        $this->assertStringContainsString(get_string('support_reference', 'block_pdfexport'), $content->text);
        $this->assertStringNotContainsString('Item hidden', $content->text);
        $this->assertSame('', $content->footer);
        // Content is computed once per block instance.
        $page = new \moodle_page();
        $page->set_course(get_course($this->course->id));
        $block = block_instance('pdfexport', $this->record, $page);
        $this->assertSame($block->get_content(), $block->get_content());
    }

    /**
     * Editing teachers also get the management link and see hidden published resources.
     */
    public function test_teacher_content(): void {
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
        $content = $this->content();
        $this->assertStringContainsString('Item hidden', $content->text);
        $this->assertStringContainsString('manage.php', $content->footer);
    }

    /**
     * Without published resources the block says so.
     */
    public function test_empty_selection(): void {
        global $DB;
        $DB->set_field('block_instances', 'configdata', '', ['id' => $this->record->id]);
        $this->record = $DB->get_record('block_instances', ['id' => $this->record->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'student'));
        $content = $this->content();
        $this->assertStringContainsString(get_string('noresources', 'block_pdfexport'), $content->text);
        $this->assertStringNotContainsString(get_string('downloadcoursepdf', 'block_pdfexport'), $content->text);
    }

    /**
     * Users without the view capability, and the front page, get an empty block.
     */
    public function test_no_content(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);
        $roleid = $this->getDataGenerator()->create_role();
        $this->getDataGenerator()->role_assign($roleid, $student->id, \context_course::instance($this->course->id));
        assign_capability('block/pdfexport:view', CAP_PROHIBIT, $roleid, \context_course::instance($this->course->id));
        $this->assertSame('', $this->content()->text);
        $this->assertSame('', $this->content(SITEID)->text);
    }

    /**
     * Block metadata and plugin lifecycle.
     */
    public function test_metadata_and_lifecycle(): void {
        $block = new \block_pdfexport();
        $this->assertSame(get_string('pluginname', 'block_pdfexport'), $block->title);
        $this->assertFalse($block->instance_allow_multiple());
        $this->assertSame(['course-view' => true, 'site-index' => false, 'my' => false], $block->applicable_formats());
        $this->assertNotNull(\core_plugin_manager::instance()->get_plugin_info('block_pdfexport')->versiondb);
        foreach (['addinstance', 'myaddinstance', 'configure', 'view'] as $capability) {
            $this->assertNotEmpty(get_capability_info('block/pdfexport:' . $capability));
        }
        $this->assertSame('privacy:metadata', privacy\provider::get_reason());
        $this->assertNotEmpty(get_string(privacy\provider::get_reason(), 'block_pdfexport'));
    }
}
