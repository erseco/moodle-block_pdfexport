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

use block_pdfexport\local\export_service;
use block_pdfexport\local\pdf_document;
use block_pdfexport\local\print_transformer;
use block_pdfexport\local\resource_detector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the export pipeline: authorisation, ordering, document model and PDF output.
 *
 * @package    block_pdfexport
 * @category   test
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(export_service::class)]
#[CoversClass(resource_detector::class)]
#[CoversClass(pdf_document::class)]
final class export_service_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass[] Users by role name. */
    private array $users = [];

    /** @var \stdClass[] Modules by key. */
    private array $cms = [];

    /**
     * Builds a course with three sections, users of every role and assorted modules.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        // The folder generator needs a real user for its draft area.
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['numsections' => 3, 'fullname' => 'Introduction to Technology',
            'shortname' => 'TECH 101', 'summary' => '<p>Course summary ñ</p>']);
        foreach ([1 => 'Topic 1', 2 => 'Topic 2', 3 => 'Topic 3'] as $number => $name) {
            $DB->set_field('course_sections', 'name', $name, ['course' => $this->course->id, 'section' => $number]);
        }
        foreach (['editingteacher', 'teacher', 'student'] as $role) {
            $this->users[$role] = $generator->create_and_enrol($this->course, $role);
        }
        $this->users['unenrolled'] = $generator->create_user();
        $create = function (string $key, string $modname, int $section, array $extra = []) use ($generator): void {
            $this->cms[$key] = $generator->create_module($modname, ['course' => $this->course->id, 'section' => $section,
                'name' => ucfirst($key)] + $extra);
        };
        // Created out of order on purpose: section 2 first.
        $create('materials', 'folder', 2);
        $create('welcome', 'page', 1, ['content' => '<p>Welcome text</p>']);
        $create('book', 'book', 1);
        $create('quiz', 'quiz', 3);
        $create('hidden', 'page', 3, ['visible' => 0]);
        $create('links', 'url', 2, ['externalurl' => 'https://example.com']);
        $bookgenerator = $generator->get_plugin_generator('mod_book');
        $bookgenerator->create_chapter(['bookid' => $this->cms['book']->id, 'pagenum' => 1, 'title' => 'Chapter 1']);
        $bookgenerator->create_chapter(['bookid' => $this->cms['book']->id, 'pagenum' => 2, 'title' => 'Chapter 2']);
    }

    /**
     * Returns course module ids.
     *
     * @param string ...$keys Module keys.
     * @return int[]
     */
    private function ids(string ...$keys): array {
        return array_map(fn(string $key): int => (int)$this->cms[$key]->cmid, $keys);
    }

    /**
     * Returns a service for the current user.
     *
     * @param int[] $selected Selection.
     * @return export_service
     */
    private function service(array $selected): export_service {
        return new export_service(get_course($this->course->id), $selected);
    }

    /**
     * Returns the item titles of a document.
     *
     * @param array $document Document model.
     * @return string[]
     */
    private static function titles(array $document): array {
        $titles = [];
        foreach ($document['sections'] as $section) {
            $titles[] = $section['title'];
            foreach ($section['items'] as $item) {
                $titles[] = $item['title'];
            }
        }
        return $titles;
    }

    /**
     * Encodes text as TCPDF writes metadata and outline strings (UTF-16BE, after a BOM).
     *
     * @param string $text UTF-8 text.
     * @return string
     */
    private static function pdf_string(string $text): string {
        return mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');
    }

    /**
     * Sections and resources follow course order, whatever the selection order; non-exportable
     * and foreign course modules are ignored.
     */
    public function test_course_document_order(): void {
        $this->setUser($this->users['student']);
        $other = $this->getDataGenerator()->create_module('page', ['course' => $this->getDataGenerator()->create_course()->id]);
        $selected = array_merge($this->ids('links', 'materials', 'book', 'welcome', 'quiz'), [(int)$other->cmid, 999999]);
        $document = $this->service($selected)->build_course_document();
        $this->assertSame('course', $document['type']);
        $this->assertSame('Introduction to Technology', $document['title']);
        $this->assertSame('TECH 101', $document['subtitle']);
        $this->assertStringContainsString('Course summary ñ', $document['summary']);
        $this->assertSame(
            ['1. Topic 1', '1.1 Welcome', '1.2 Book', '2. Topic 2', '2.1 Materials', '2.2 Links'],
            self::titles($document)
        );
        $book = $document['sections'][0]['items'][1];
        $this->assertSame(['1. Chapter 1', '2. Chapter 2'], array_column($book['parts'], 'title'));
        $this->assertSame('full', $book['support']);
        $this->assertSame('book', $book['modname']);
    }

    /**
     * Hidden, restricted and group-restricted resources are never exported for students, and
     * nothing reveals them; staff with the right capabilities see the hidden ones.
     */
    public function test_visibility_and_availability(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $group = $generator->create_group(['courseid' => $this->course->id]);
        $future = $generator->create_module('page', ['course' => $this->course->id, 'section' => 3, 'name' => 'Future',
            'availability' => json_encode(\core_availability\tree::get_root_json(
                [\availability_date\condition::get_json('>=', time() + DAYSECS)],
                \core_availability\tree::OP_AND,
                false
            ))]);
        $grouponly = $generator->create_module('page', ['course' => $this->course->id, 'section' => 3, 'name' => 'Group',
            'availability' => json_encode(\core_availability\tree::get_root_json(
                [\availability_group\condition::get_json($group->id)],
                \core_availability\tree::OP_AND,
                false
            ))]);
        $hiddensection = $generator->create_module('page', ['course' => $this->course->id, 'section' => 2,
            'name' => 'Insection']);
        \core_courseformat\formatactions::section($this->course->id)->set_visibility(
            get_fast_modinfo($this->course->id)->get_section_info(2),
            false
        );
        // Restore the visibility of the modules that set_section_visible() hid, except the one we test.
        foreach ($this->ids('materials', 'links') as $id) {
            $DB->set_field('course_modules', 'visible', 1, ['id' => $id]);
        }
        rebuild_course_cache($this->course->id, true);
        $selected = array_merge(
            $this->ids('welcome', 'hidden', 'materials', 'links'),
            [(int)$future->cmid, (int)$grouponly->cmid, (int)$hiddensection->cmid]
        );

        $this->setUser($this->users['student']);
        $titles = self::titles($this->service($selected)->build_course_document());
        $this->assertSame(['1. Topic 1', '1.1 Welcome'], $titles);

        $generator->create_group_member(['groupid' => $group->id, 'userid' => $this->users['student']->id]);
        get_fast_modinfo(0, 0, true);
        $titles = self::titles($this->service($selected)->build_course_document());
        $this->assertSame(['1. Topic 1', '1.1 Welcome', '2. Topic 3', '2.1 Group'], $titles);

        $this->setUser($this->users['editingteacher']);
        $titles = self::titles($this->service($selected)->build_course_document());
        $this->assertContains('2.1 Materials', $titles);
        $this->assertContains('3.1 Hidden', $titles);
        $this->assertContains('3.2 Future', $titles);
    }

    /**
     * Module capability overrides are honoured at export time.
     */
    public function test_capability_override(): void {
        $this->setUser($this->users['student']);
        $role = $this->getDataGenerator()->create_role();
        $this->getDataGenerator()->role_assign(
            $role,
            $this->users['student']->id,
            \context_module::instance($this->cms['welcome']->cmid)
        );
        assign_capability('mod/page:view', CAP_PROHIBIT, $role, \context_module::instance($this->cms['welcome']->cmid));
        $titles = self::titles($this->service($this->ids('welcome', 'book'))->build_course_document());
        $this->assertSame(['1. Topic 1', '1.1 Book'], $titles);
    }

    /**
     * An empty or inaccessible selection is reported instead of producing an empty PDF.
     *
     * @param string $user User key.
     * @param string[] $keys Selected module keys.
     */
    #[DataProvider('nothing_to_export_provider')]
    public function test_nothing_to_export(string $user, array $keys): void {
        $this->setUser($this->users[$user]);
        $this->expectExceptionMessage(get_string('nothingtoexport', 'block_pdfexport'));
        $this->service($this->ids(...$keys))->build_course_document();
    }

    /**
     * Cases with nothing to export.
     *
     * @return array
     */
    public static function nothing_to_export_provider(): array {
        return [
            'empty selection' => ['student', []],
            'only hidden' => ['student', ['hidden']],
            'only unsupported' => ['editingteacher', ['quiz']],
            'unenrolled user' => ['unenrolled', ['welcome']],
        ];
    }

    /**
     * Guests see published content of courses that allow guest access only.
     */
    public function test_guest(): void {
        $this->setGuestUser();
        $this->expectExceptionMessage(get_string('nothingtoexport', 'block_pdfexport'));
        $this->service($this->ids('welcome'))->build_course_document();
    }

    /**
     * Single-resource exports only work for published, accessible modules of the course.
     *
     * @param string $key Module key ('other' for a module of another course, 'none' for an unknown id).
     */
    #[DataProvider('module_not_published_provider')]
    public function test_module_not_published(string $key): void {
        $this->setUser($this->users['student']);
        $other = $this->getDataGenerator()->create_module('page', ['course' => $this->getDataGenerator()->create_course()->id]);
        $cmid = ['other' => (int)$other->cmid, 'none' => 999999][$key] ?? (int)$this->cms[$key]->cmid;
        $this->expectExceptionMessage(get_string('notpublished', 'block_pdfexport'));
        $this->service(array_merge($this->ids('welcome', 'hidden', 'quiz'), [(int)$other->cmid]))
            ->build_module_document($cmid);
    }

    /**
     * Unpublished, hidden, unsupported and foreign modules.
     *
     * @return array
     */
    public static function module_not_published_provider(): array {
        return [
            'not selected' => ['book'],
            'hidden' => ['hidden'],
            'unsupported' => ['quiz'],
            'other course' => ['other'],
            'unknown id' => ['none'],
        ];
    }

    /**
     * A single book export contains its chapters as parts.
     */
    public function test_module_document(): void {
        $this->setUser($this->users['student']);
        $service = $this->service($this->ids('welcome', 'book'));
        $document = $service->build_module_document((int)$this->cms['book']->cmid);
        $this->assertSame('module', $document['type']);
        $this->assertSame('Book', $document['title']);
        $this->assertSame('Introduction to Technology', $document['subtitle']);
        $this->assertNull($document['sections'][0]['title']);
        $this->assertCount(2, $document['sections'][0]['items'][0]['parts']);
        $this->assertSame('TECH-101-Book.pdf', $service->get_filename($document));
        $pdf = $service->render($document);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString(self::pdf_string('Chapter 2'), $pdf);
    }

    /**
     * Course exports render a valid PDF with cover, outline, TOC and metadata.
     */
    public function test_render_course(): void {
        $this->setUser($this->users['student']);
        $service = $this->service($this->ids('welcome', 'book', 'materials', 'links'));
        $document = $service->build_course_document();
        $pdf = $service->render($document);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringEndsWith("%%EOF\n", $pdf);
        foreach (['Introduction to Technology', '1. Topic 1', '1.2 Book', '1. Chapter 1', '2.2 Links'] as $text) {
            $this->assertStringContainsString(self::pdf_string($text), $pdf);
        }
        $this->assertStringContainsString(self::pdf_string(get_string('pluginname', 'block_pdfexport') . ' '), $pdf);
        // Cover, TOC and at least one page per section.
        $this->assertGreaterThanOrEqual(4, preg_match_all('~/Type /Page\b~', $pdf));
        $this->assertSame('TECH-101.pdf', $service->get_filename($document));
    }

    /**
     * Conversion warnings are attached to their resource and printed in an appendix.
     */
    public function test_warnings_and_failures(): void {
        global $DB;
        $this->setUser($this->users['student']);
        $scorm = $this->getDataGenerator()->create_module('h5pactivity', ['course' => $this->course->id, 'section' => 3,
            'name' => 'Interactive']);
        $DB->delete_records('page', ['id' => $this->cms['welcome']->id]);
        $this->assertDebuggingNotCalled();
        $service = $this->service(array_merge($this->ids('welcome'), [(int)$scorm->cmid]));
        $document = $service->build_course_document();
        $this->assertDebuggingCalled();
        $this->assertSame([
            '1.1 Welcome: ' . get_string('exportfailed', 'block_pdfexport'),
            '2.1 Interactive: ' . get_string('referenceonly', 'block_pdfexport'),
        ], $document['warnings']);
        $this->assertSame('reference', $document['sections'][1]['items'][0]['support']);
        $pdf = $service->render($document);
        $this->assertStringContainsString(self::pdf_string(get_string('conversionwarnings', 'block_pdfexport')), $pdf);
    }

    /**
     * Selections above the limit are refused with an explanation.
     */
    public function test_too_many_resources(): void {
        $this->setUser($this->users['student']);
        $service = new export_service(
            get_course($this->course->id),
            $this->ids('welcome', 'book'),
            new print_transformer(),
            1
        );
        $this->expectExceptionMessage(get_string('toomanyresources', 'block_pdfexport', ['count' => 2, 'max' => 1]));
        $service->build_course_document();
    }

    /**
     * File names are predictable and safe.
     *
     * @param string $shortname Course short name.
     * @param string $title Module title.
     * @param string $expected Expected file name.
     */
    #[DataProvider('filename_provider')]
    public function test_filename(string $shortname, string $title, string $expected): void {
        $service = new export_service((object)['shortname' => $shortname], []);
        $this->assertSame($expected, $service->get_filename(['type' => $title === '' ? 'course' : 'module',
            'title' => $title]));
    }

    /**
     * File name cases.
     *
     * @return array
     */
    public static function filename_provider(): array {
        return [
            'course' => ['MATH 1', '', 'MATH-1.pdf'],
            'module' => ['MATH', 'Unit 1: Ñandú', 'MATH-Unit-1-Ñandú.pdf'],
            'traversal' => ['../../etc', 'a/b\\c', 'etc-abc.pdf'],
            'html' => ['<b>X</b>', '', 'X.pdf'],
            'empty' => ['...', '', 'course.pdf'],
        ];
    }

    /**
     * Modules in a subsection are exported in place, inside the parent section.
     */
    public function test_subsections(): void {
        if (!\core_component::get_component_directory('mod_subsection')) {
            $this->markTestSkipped('mod_subsection is not installed.');
        }
        $this->setUser($this->users['student']);
        $generator = $this->getDataGenerator();
        $subsection = $generator->create_module('subsection', ['course' => $this->course->id, 'section' => 1]);
        $delegated = get_fast_modinfo($this->course->id)->get_cm($subsection->cmid)->get_delegated_section_info();
        $inner = $generator->create_module('page', ['course' => $this->course->id, 'section' => $delegated->sectionnum,
            'name' => 'Inner']);
        $after = $generator->create_module('page', ['course' => $this->course->id, 'section' => 1, 'name' => 'After']);
        $selected = array_merge($this->ids('welcome'), [(int)$inner->cmid, (int)$after->cmid]);
        $this->assertSame(
            ['1. Topic 1', '1.1 Welcome', '1.2 Inner', '1.3 After'],
            self::titles($this->service($selected)->build_course_document())
        );
        // Discovery without filters lists everything, hidden modules included.
        $all = resource_detector::get_sections(get_fast_modinfo($this->course->id), false);
        $this->assertCount(3, $all);
        $this->assertSame('Topic 3', $all[2]['name']);
    }
}
