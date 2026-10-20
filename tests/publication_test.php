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

use block_pdfexport\form\selection_form;
use block_pdfexport\local\publication;
use block_pdfexport\local\resource_detector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the published selection: configuration storage, validation and the selection form.
 *
 * @package    block_pdfexport
 * @category   test
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(publication::class)]
#[CoversClass(selection_form::class)]
final class publication_test extends \advanced_testcase {
    /**
     * Parsing tolerates missing, tampered and legacy configuration.
     *
     * @param mixed $config Block configuration.
     * @param int[] $expected Expected ids.
     */
    #[DataProvider('config_provider')]
    public function test_get_selected_cmids($config, array $expected): void {
        $this->assertSame($expected, publication::get_selected_cmids($config));
    }

    /**
     * Configuration cases.
     *
     * @return array
     */
    public static function config_provider(): array {
        return [
            'null' => [null, []],
            'string' => ['1,2', []],
            'no property' => [(object)['other' => 1], []],
            'array property' => [(object)['selectedcmids' => [1, 2]], []],
            'empty' => [(object)['selectedcmids' => ''], []],
            'alpha format' => [(object)['selectedcmids' => '12,7,30'], [12, 7, 30]],
            'garbage' => [(object)['selectedcmids' => '4,a,-3,4,,0,9x'], [4, 9]],
            'integer' => [(object)['selectedcmids' => 5], [5]],
        ];
    }

    /**
     * Saving keeps other properties and is readable through the block API.
     */
    public function test_save_and_backward_compatibility(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('block_pdfexport');
        $record = $generator->create_course_block($course);
        // Configuration exactly as saved by the 0.3.x alpha manage.php.
        $legacy = base64_encode(serialize((object)['selectedcmids' => '3,4', 'title' => 'Mine']));
        $DB->set_field('block_instances', 'configdata', $legacy, ['id' => $record->id]);

        $block = publication::get_course_block($record->id, $course->id);
        $this->assertSame([3, 4], publication::get_selected_cmids($block->config));
        publication::save($block, [8, 8, 9, -1]);
        $this->assertSame([8, 9], publication::get_selected_cmids($block->config));

        $reloaded = publication::get_course_block($record->id, $course->id);
        $this->assertSame('8,9', $reloaded->config->selectedcmids);
        $this->assertSame('Mine', $reloaded->config->title);

        $course2 = $this->getDataGenerator()->create_course();
        $record2 = $generator->create_course_block($course2);
        $DB->set_field('block_instances', 'configdata', '', ['id' => $record2->id]);
        $fresh = publication::get_course_block($record2->id, $course2->id);
        $this->assertSame([], publication::get_selected_cmids($fresh->config));
        publication::save($fresh, [1]);
        $this->assertSame([1], publication::get_selected_cmids($fresh->config));
    }

    /**
     * Block ids are validated against the course: substitution and foreign blocks are refused.
     */
    public function test_get_course_block_validation(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $other = $generator->create_course();
        $block = $generator->get_plugin_generator('block_pdfexport')->create_course_block($course);
        $html = $generator->create_block('html', ['parentcontextid' => \context_course::instance($course->id)->id]);
        $front = $generator->get_plugin_generator('block_pdfexport')->create_instance(
            ['parentcontextid' => \context_course::instance(SITEID)->id]
        );
        $this->assertSame((int)$block->id, (int)publication::get_course_block($block->id, $course->id)->instance->id);

        $cases = [[$block->id, $other->id], [$html->id, $course->id], [$front->id, SITEID], [999999, $course->id],
            [$block->id, 999999]];
        foreach ($cases as [$blockid, $courseid]) {
            try {
                publication::get_course_block($blockid, $courseid);
                $this->fail('Block ' . $blockid . ' accepted for course ' . $courseid);
            } catch (\moodle_exception $e) {
                $this->assertSame('invalidblock', $e->errorcode);
            }
        }
        $this->assertTrue($DB->record_exists('block_instances', ['id' => $block->id]));
    }

    /**
     * The selection form lists modules by section and only accepts listed ids.
     */
    public function test_selection_form(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 1]);
        $book = $this->getDataGenerator()->create_module('book', ['course' => $course->id, 'section' => 2]);
        $this->getDataGenerator()->create_module('quiz', ['course' => $course->id, 'section' => 2]);
        $sections = resource_detector::get_sections(get_fast_modinfo($course), false);
        $customdata = ['sections' => $sections, 'selected' => [(int)$book->cmid]];

        $form = new selection_form(null, $customdata);
        $html = $form->render();
        $this->assertStringContainsString('name="cm[' . $page->cmid . ']"', $html);
        $this->assertStringContainsString('name="cm[' . $book->cmid . ']"', $html);
        $this->assertStringContainsString(get_string('support_full', 'block_pdfexport'), $html);
        $this->assertStringNotContainsString('Quiz', $html);
        $this->assertMatchesRegularExpression('~type="checkbox"\s+name="cm\[' . $book->cmid . '\]"[^>]*checked~', $html);
        $this->assertDoesNotMatchRegularExpression('~type="checkbox"\s+name="cm\[' . $page->cmid . '\]"[^>]*checked~', $html);

        selection_form::mock_submit(['cm' => [$page->cmid => 1, $book->cmid => 0, 999999 => 1]]);
        $form = new selection_form(null, $customdata);
        $data = $form->get_data();
        $this->assertSame([(int)$page->cmid], $form->get_selected_cmids($data));
        $this->assertSame([], $form->get_selected_cmids((object)[]));
    }
}
