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

use block_pdfexport\local\adapters\exelearning_adapter;
use block_pdfexport\local\export_service;
use block_pdfexport\local\print_transformer;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the eXeLearning (mod_exelearning) adapter.
 *
 * Packages come from tests/fixtures (copied from mod_exelearning's own fixtures); some tests
 * replace the extracted files to check specific markup.
 *
 * @package    block_pdfexport
 * @category   test
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(exelearning_adapter::class)]
final class exelearning_adapter_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /**
     * Skips when mod_exelearning is not installed; creates a course otherwise.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!\core_component::get_component_directory('mod_exelearning')) {
            $this->markTestSkipped('mod_exelearning is not installed.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Creates an eXeLearning activity from a fixture package.
     *
     * @param string $fixture File name in tests/fixtures.
     * @param array $record Extra module fields.
     * @return \cm_info
     */
    private function activity(string $fixture, array $record = []): \cm_info {
        $module = $this->getDataGenerator()->create_module('exelearning', ['course' => $this->course->id,
            'name' => 'eXe activity', 'packagefilepath' => __DIR__ . '/fixtures/' . $fixture] + $record);
        return get_fast_modinfo($this->course->id)->get_cm($module->cmid);
    }

    /**
     * Replaces (or adds) a file of the extracted package.
     *
     * @param \cm_info $cm Activity.
     * @param string $path Path inside the package.
     * @param string $content File content.
     */
    private static function put(\cm_info $cm, string $path, string $content): void {
        global $DB;
        $revision = (int)$DB->get_field('exelearning', 'revision', ['id' => $cm->instance]);
        $dir = dirname($path) === '.' ? '/' : '/' . dirname($path) . '/';
        $fs = get_file_storage();
        $record = ['contextid' => $cm->context->id, 'component' => 'mod_exelearning', 'filearea' => 'content',
            'itemid' => $revision, 'filepath' => $dir, 'filename' => basename($path)];
        if ($file = $fs->get_file(...array_values($record))) {
            $file->delete();
        }
        $fs->create_file_from_string($record, $content);
    }

    /**
     * Returns PNG bytes.
     *
     * @return string
     */
    private static function png(): string {
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($image);
        return ob_get_clean();
    }

    /**
     * Pages follow the package navigation; text iDevices are printed and interactive ones replaced.
     */
    public function test_multipage_package(): void {
        $cm = $this->activity('multipage-gradable.elpx', ['intro' => '<p>Activity intro</p>']);
        $transformer = new print_transformer();
        $parts = (new exelearning_adapter())->export($cm, $transformer);

        $this->assertSame([null, 'Page One', 'Page Two', null], array_column($parts, 'title'));
        $this->assertSame([0, 1, 1, 0], array_column($parts, 'level'));
        $this->assertStringContainsString('Activity intro', $parts[0]['html']);
        $this->assertStringContainsString('Some text.', $parts[1]['html']);
        $this->assertStringContainsString('More text.', $parts[2]['html']);
        // Interactive iDevices keep their title but not their content.
        $this->assertStringContainsString('True or false 1', $parts[1]['html']);
        $this->assertStringNotContainsString('Question one.', $parts[1]['html']);
        $this->assertStringNotContainsString('Question two.', $parts[2]['html']);
        $this->assertStringContainsString('[Interactive activity omitted:', $parts[1]['html']);
        $this->assertStringContainsString(get_string('exelearningnotice', 'block_pdfexport'), $parts[3]['html']);
        $this->assertStringContainsString('/mod/exelearning/view.php?id=' . $cm->id, $parts[3]['html']);
        $this->assertSame([get_string('warning_interactive', 'block_pdfexport')], $transformer->take_warnings());
        $this->assertSame('partial', (new exelearning_adapter())->get_support_level());
    }

    /**
     * A real eXeLearning export never leaks game data or answers kept in hidden markup.
     */
    public function test_real_package_hides_game_data(): void {
        $cm = $this->activity('actividad-evaluable.elpx');
        $parts = (new exelearning_adapter())->export($cm, new print_transformer());
        $this->assertSame('Test 1', $parts[1]['title']);
        $html = implode('', array_column($parts, 'html'));
        $this->assertStringContainsString('Verdadero o falso', $html);
        $this->assertStringNotContainsString('Te gusta', $html);
        $this->assertStringNotContainsString('typeGame', $html);
        $this->assertStringNotContainsString('Toggle content', $html);
    }

    /**
     * Nested pages, package images, hidden feedback, links and traversal attempts.
     */
    public function test_page_markup(): void {
        $cm = $this->activity('multipage-gradable.elpx');
        self::put($cm, 'index.html', '<html><body><nav id="siteNav"><ul>' .
            '<li><a href="index.html">Home</a><ul><li><a href="html/sub.html">Sub page</a></li></ul></li>' .
            '<li><a href="https://example.com/x.html">External</a></li><li><a href="../../escape.html">Escape</a></li>' .
            '<li><a href="content/file.pdf">Not a page</a></li><li><a href="html/missing.html">Missing</a></li></ul></nav>' .
            '<main><header class="main-header"><h1 class="page-title">Home</h1></header><div class="page-content">' .
            '<article class="box"><header class="box-head"><div class="box-icon"><img src="theme/icons/i.svg"></div>' .
            '<h1 class="box-title">Reading</h1><button>Toggle content</button></header><div class="box-content">' .
            '<div class="idevice_node text" data-idevice-type="text"><p>Static text ñ</p>' .
            '<p><img src="content/resources/pic%201.png" alt="Pic"></p>' .
            '<div class="feedback js-hidden">Hidden feedback</div>' .
            '<p><a href="html/sub.html">Go to sub page</a> <a href="https://moodle.org">Moodle</a> ' .
            '<a href="#top">Top</a></p><p><img src="../../../etc/x.png" alt="Escape"></p></div></div></article>' .
            '</div></main></body></html>');
        self::put($cm, 'html/sub.html', '<html><body><main><div class="page-content"><p>Sub content</p>' .
            '<p><img src="../content/resources/pic%201.png" alt="Up"></p></div></main></body></html>');
        self::put($cm, 'content/resources/pic 1.png', self::png());

        $transformer = new print_transformer();
        $parts = (new exelearning_adapter())->export($cm, $transformer);
        $this->assertSame([null, 'Home', 'Sub page', 'Missing', null], array_column($parts, 'title'));
        $this->assertSame([0, 1, 2, 1, 0], array_column($parts, 'level'));
        $home = $parts[1]['html'];
        $this->assertStringContainsString('Static text ñ', $home);
        $this->assertStringContainsString('Reading', $home);
        $this->assertStringContainsString('<img src="@', $home);
        $this->assertStringNotContainsString('Hidden feedback', $home);
        $this->assertStringNotContainsString('Toggle content', $home);
        $this->assertStringContainsString('Go to sub page', $home);
        $this->assertStringNotContainsString('sub.html', $home);
        $this->assertStringContainsString('<a href="https://moodle.org">Moodle</a>', $home);
        $this->assertStringContainsString(get_string('imagenotincluded', 'block_pdfexport', 'Escape'), $home);
        $this->assertStringContainsString('Sub content', $parts[2]['html']);
        $this->assertStringContainsString('<img src="@', $parts[2]['html']);
        $this->assertSame('', $parts[3]['html']);
        $this->assertSame([get_string('warning_imagenotfound', 'block_pdfexport')], $transformer->take_warnings());
    }

    /**
     * Packages without navigation export index.html as a single page; missing packages say so.
     */
    public function test_fallbacks(): void {
        $cm = $this->activity('multipage-gradable.elpx');
        self::put($cm, 'index.html', '<html><body><main><p>Only page</p></main></body></html>');
        $parts = (new exelearning_adapter())->export($cm, new print_transformer());
        $this->assertSame(get_string('exelearningpage', 'block_pdfexport'), $parts[1]['title']);
        $this->assertStringContainsString('Only page', $parts[1]['html']);

        self::put($cm, 'index.html', '<html><body><p>No main element</p></body></html>');
        $parts = (new exelearning_adapter())->export($cm, new print_transformer());
        $this->assertSame('', $parts[1]['html']);

        get_file_storage()->delete_area_files($cm->context->id, 'mod_exelearning', 'content');
        $parts = (new exelearning_adapter())->export($cm, new print_transformer());
        $this->assertCount(1, $parts);
        $this->assertStringContainsString(get_string('exportfailed', 'block_pdfexport'), $parts[0]['html']);
    }

    /**
     * Students export visible activities they can view; the course PDF includes the pages.
     */
    public function test_course_export(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $visible = $this->activity('multipage-gradable.elpx');
        $hidden = $this->activity('multipage-gradable.elpx', ['visible' => 0]);
        $this->setUser($student);
        $service = new export_service(get_course($this->course->id), [(int)$visible->id, (int)$hidden->id]);
        $document = $service->build_course_document();
        $this->assertCount(1, $document['sections'][0]['items']);
        $item = $document['sections'][0]['items'][0];
        $this->assertSame('exelearning', $item['modname']);
        $this->assertSame('partial', $item['support']);
        $pdf = $service->render($document);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString(mb_convert_encoding('Page Two', 'UTF-16BE', 'UTF-8'), $pdf);
    }
}
