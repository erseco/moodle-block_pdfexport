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

use block_pdfexport\local\adapters\base_adapter;
use block_pdfexport\local\adapters\book_adapter;
use block_pdfexport\local\adapters\folder_adapter;
use block_pdfexport\local\adapters\label_adapter;
use block_pdfexport\local\adapters\page_adapter;
use block_pdfexport\local\adapters\reference_adapter;
use block_pdfexport\local\adapters\resource_adapter;
use block_pdfexport\local\adapters\url_adapter;
use block_pdfexport\local\print_transformer;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the per-module content adapters.
 *
 * @package    block_pdfexport
 * @category   test
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(base_adapter::class)]
#[CoversClass(book_adapter::class)]
#[CoversClass(folder_adapter::class)]
#[CoversClass(label_adapter::class)]
#[CoversClass(page_adapter::class)]
#[CoversClass(reference_adapter::class)]
#[CoversClass(resource_adapter::class)]
#[CoversClass(url_adapter::class)]
final class adapters_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Student. */
    private \stdClass $student;

    /**
     * Creates a course with a student.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
    }

    /**
     * Creates a module and returns it as cm_info for the current user.
     *
     * @param string $modname Module name.
     * @param array $record Module record.
     * @return \cm_info
     */
    private function cm(string $modname, array $record = []): \cm_info {
        $module = $this->getDataGenerator()->create_module($modname, ['course' => $this->course->id] + $record);
        return get_fast_modinfo($this->course->id)->get_cm($module->cmid);
    }

    /**
     * Returns PNG bytes.
     *
     * @return string
     */
    private static function png(): string {
        $image = imagecreatetruecolor(8, 8);
        ob_start();
        imagepng($image);
        return ob_get_clean();
    }

    /**
     * Stores a file in a module area.
     *
     * @param \cm_info $cm Module.
     * @param string $component Component.
     * @param string $area File area.
     * @param string $filename File name.
     * @param string $content Content.
     * @param string $filepath File path.
     */
    private static function store(
        \cm_info $cm,
        string $component,
        string $area,
        string $filename,
        string $content,
        string $filepath = '/'
    ): void {
        get_file_storage()->create_file_from_string(['contextid' => $cm->context->id, 'component' => $component,
            'filearea' => $area, 'itemid' => 0, 'filepath' => $filepath, 'filename' => $filename], $content);
    }

    /**
     * The registry maps module types to adapters and support levels.
     */
    public function test_registry(): void {
        $expected = ['page' => 'full', 'book' => 'full', 'label' => 'full', 'resource' => 'partial',
            'folder' => 'partial', 'url' => 'full', 'scorm' => 'reference', 'h5pactivity' => 'reference',
            'exelearning' => 'partial'];
        $this->assertSame(array_keys($expected), base_adapter::get_module_names());
        foreach ($expected as $modname => $support) {
            $this->assertSame($support, base_adapter::for_module($modname)->get_support_level(), $modname);
        }
        $this->assertNull(base_adapter::for_module('quiz'));
        $this->assertNull(base_adapter::for_module('assign'));
        $this->assertNull(base_adapter::for_module('forum'));
    }

    /**
     * Pages export their content with embedded images and with filters applied.
     */
    public function test_page(): void {
        $cm = $this->cm('page', ['content' => '<p>Hello <img src="@@PLUGINFILE@@/a.png" alt="A"></p>']);
        self::store($cm, 'mod_page', 'content', 'a.png', self::png());
        $parts = (new page_adapter())->export($cm, new print_transformer());
        $this->assertCount(1, $parts);
        $this->assertNull($parts[0]['title']);
        $this->assertSame(0, $parts[0]['level']);
        $this->assertStringContainsString('Hello <img src="@', $parts[0]['html']);
    }

    /**
     * Books export chapters in order, numbered, with levels; hidden chapters only for staff.
     */
    public function test_book(): void {
        $cm = $this->cm('book', ['numbering' => 0]);
        $bookgenerator = $this->getDataGenerator()->get_plugin_generator('mod_book');
        $bookid = $cm->instance;
        $last = $bookgenerator->create_chapter(['bookid' => $bookid, 'pagenum' => 1, 'title' => 'Last',
            'content' => '<p>Last text</p>']);
        $first = $bookgenerator->create_chapter(['bookid' => $bookid, 'pagenum' => 1, 'title' => 'First',
            'content' => '<p><img src="@@PLUGINFILE@@/c.png"></p>']);
        $bookgenerator->create_chapter(['bookid' => $bookid, 'pagenum' => 2, 'subchapter' => 1, 'title' => 'Sub & sub']);
        $bookgenerator->create_chapter(['bookid' => $bookid, 'pagenum' => 4, 'hidden' => 1, 'title' => 'Secret']);
        get_file_storage()->create_file_from_string(['contextid' => $cm->context->id, 'component' => 'mod_book',
            'filearea' => 'chapter', 'itemid' => $first->id, 'filepath' => '/', 'filename' => 'c.png'], self::png());
        // An image of another chapter must not resolve.
        get_file_storage()->create_file_from_string(['contextid' => $cm->context->id, 'component' => 'mod_book',
            'filearea' => 'chapter', 'itemid' => $last->id, 'filepath' => '/', 'filename' => 'c.png'], 'x');

        $parts = (new book_adapter())->export($cm, new print_transformer());
        $this->assertSame(['First', 'Sub & sub', 'Last', 'Secret'], array_column($parts, 'title'));
        $this->assertSame([1, 2, 1, 1], array_column($parts, 'level'));
        $this->assertStringContainsString('<img src="@', $parts[0]['html']);
        $this->assertStringContainsString('Last text', $parts[2]['html']);

        $this->setUser($this->student);
        $cm = get_fast_modinfo($this->course->id)->get_cm($cm->id);
        $this->assertTrue((new book_adapter())->can_access($cm));
        $parts = (new book_adapter())->export($cm, new print_transformer());
        $this->assertSame(['First', 'Sub & sub', 'Last'], array_column($parts, 'title'));
    }

    /**
     * Empty books say so instead of producing an empty document.
     */
    public function test_empty_book(): void {
        $parts = (new book_adapter())->export($this->cm('book'), new print_transformer());
        $this->assertStringContainsString(get_string('emptybook', 'block_pdfexport'), $parts[0]['html']);
    }

    /**
     * Labels export their text.
     */
    public function test_label(): void {
        $parts = (new label_adapter())->export($this->cm('label', ['intro' => '<p>Label text</p>']), new print_transformer());
        $this->assertStringContainsString('<p>Label text</p>', $parts[0]['html']);
    }

    /**
     * File resources embed PNG/JPEG images and link anything else.
     */
    public function test_resource(): void {
        $image = $this->cm('resource', ['name' => 'Poster']);
        get_file_storage()->delete_area_files($image->context->id, 'mod_resource', 'content');
        self::store($image, 'mod_resource', 'content', 'p.png', self::png());
        $html = (new resource_adapter())->export($image, new print_transformer())[0]['html'];
        $this->assertStringContainsString('<img src="@', $html);
        $this->assertStringContainsString('alt="Poster"', $html);

        $pdf = $this->cm('resource');
        get_file_storage()->delete_area_files($pdf->context->id, 'mod_resource', 'content');
        self::store($pdf, 'mod_resource', 'content', 'notes.pdf', '%PDF-1.4');
        $html = (new resource_adapter())->export($pdf, new print_transformer())[0]['html'];
        $this->assertStringContainsString('<strong>notes.pdf</strong>', $html);
        $this->assertStringContainsString('/pluginfile.php/' . $pdf->context->id . '/mod_resource/content/0/notes.pdf', $html);
        $this->assertStringContainsString(get_string('filenotembedded', 'block_pdfexport'), $html);

        $empty = $this->cm('resource');
        get_file_storage()->delete_area_files($empty->context->id, 'mod_resource', 'content');
        $html = (new resource_adapter())->export($empty, new print_transformer())[0]['html'];
        $this->assertStringContainsString(get_string('exportfailed', 'block_pdfexport'), $html);
    }

    /**
     * Folders list their files (with subdirectories) as links, up to a limit.
     */
    public function test_folder(): void {
        $cm = $this->cm('folder', ['intro' => '<p>Folder intro</p>']);
        self::store($cm, 'mod_folder', 'content', 'b.txt', 'b');
        self::store($cm, 'mod_folder', 'content', 'a.txt', 'a', '/sub/');
        $html = (new folder_adapter())->export($cm, new print_transformer())[0]['html'];
        $this->assertStringContainsString('Folder intro', $html);
        $this->assertStringContainsString('>b.txt</a>', $html);
        $this->assertStringContainsString('>sub/a.txt</a>', $html);
        $this->assertStringContainsString(get_string('foldernotembedded', 'block_pdfexport'), $html);
        $this->assertLessThan(strpos($html, 'sub/a.txt'), strpos($html, '>b.txt'));

        $empty = $this->cm('folder');
        $html = (new folder_adapter())->export($empty, new print_transformer())[0]['html'];
        $this->assertStringContainsString(get_string('emptyfolder', 'block_pdfexport'), $html);
    }

    /**
     * Folder listings are truncated above the limit.
     */
    public function test_folder_limit(): void {
        $cm = $this->cm('folder');
        for ($i = 0; $i <= folder_adapter::MAX_FILES; $i++) {
            self::store($cm, 'mod_folder', 'content', 'f' . $i . '.txt', 'x');
        }
        $html = (new folder_adapter())->export($cm, new print_transformer())[0]['html'];
        $this->assertSame(folder_adapter::MAX_FILES, substr_count($html, '<li>'));
        $this->assertStringContainsString(get_string('foldertruncated', 'block_pdfexport', 1), $html);
    }

    /**
     * URLs print the stored address only, never the configured parameters.
     */
    public function test_url(): void {
        $cm = $this->cm('url', ['externalurl' => 'https://example.com/page', 'intro' => '<p>Read this</p>',
            'parameters' => serialize(['uid' => 'userid'])]);
        $html = (new url_adapter())->export($cm, new print_transformer())[0]['html'];
        $this->assertStringContainsString('<a href="https://example.com/page">https://example.com/page</a>', $html);
        $this->assertStringContainsString('Read this', $html);
        $this->assertStringNotContainsString('uid=', $html);

        $unsafe = $this->cm('url', ['externalurl' => 'ftp://example.com/file']);
        $html = (new url_adapter())->export($unsafe, new print_transformer())[0]['html'];
        $this->assertStringContainsString('<p>ftp://example.com/file</p>', $html);
    }

    /**
     * Interactive activities are described and linked, with a warning.
     */
    public function test_reference(): void {
        $cm = $this->cm('h5pactivity', ['intro' => '<p>Play it</p>']);
        $transformer = new print_transformer();
        $html = (new reference_adapter())->export($cm, $transformer)[0]['html'];
        $this->assertStringContainsString('Play it', $html);
        $this->assertStringContainsString(get_string('referenceonly', 'block_pdfexport'), $html);
        $this->assertStringContainsString('/mod/h5pactivity/view.php?id=' . $cm->id, $html);
        $this->assertSame([get_string('referenceonly', 'block_pdfexport')], $transformer->take_warnings());
    }

    /**
     * Access requires visibility and the module view capability, when the module defines one.
     */
    public function test_can_access(): void {
        $page = $this->cm('page');
        $hidden = $this->cm('page', ['visible' => 0]);
        $scorm = $this->cm('url');
        $this->setUser($this->student);
        $modinfo = get_fast_modinfo($this->course->id);
        $adapter = new page_adapter();
        $this->assertTrue($adapter->can_access($modinfo->get_cm($page->id)));
        $this->assertFalse($adapter->can_access($modinfo->get_cm($hidden->id)));
        $this->assertTrue((new reference_adapter())->can_access($modinfo->get_cm($scorm->id)));

        $role = $this->getDataGenerator()->create_role();
        $this->getDataGenerator()->role_assign($role, $this->student->id, $page->context);
        assign_capability('mod/page:view', CAP_PROHIBIT, $role, $page->context);
        $this->assertFalse($adapter->can_access(get_fast_modinfo($this->course->id)->get_cm($page->id)));

        // A module type without a view capability only needs to be visible.
        $method = new \ReflectionMethod(reference_adapter::class, 'get_view_capability');
        $this->assertNull($method->invoke(new reference_adapter(), 'nonexistentmodule'));
        $this->assertSame('mod/h5pactivity:view', $method->invoke(new reference_adapter(), 'h5pactivity'));
    }
}
