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
use block_pdfexport\local\publication;
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
     * @param \stdClass|null $config Block configuration holding the attribution.
     * @return export_service
     */
    private function service(array $selected, ?\stdClass $config = null): export_service {
        return new export_service(
            get_course($this->course->id),
            $selected,
            attribution: $config ? publication::get_attribution($config) : [],
            cmattributions: publication::get_cm_attributions($config)
        );
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
     * Decodes a PDF literal string as TCPDF writes it: escaped, UTF-16BE with an optional BOM.
     *
     * @param string $literal String bytes between the parentheses.
     * @return string UTF-8 text.
     */
    private static function decode_pdf_string(string $literal): string {
        $bytes = preg_replace_callback('/\\\\([0-7]{1,3}|.)/s', function (array $match): string {
            $map = ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\f"];
            return ctype_digit($match[1]) ? chr(octdec($match[1])) : ($map[$match[1]] ?? $match[1]);
        }, $literal);
        return mb_convert_encoding(preg_replace('/^\xFE\xFF/', '', $bytes), 'UTF-8', 'UTF-16BE');
    }

    /**
     * Returns one entry of the PDF document information dictionary.
     *
     * @param string $pdf PDF bytes.
     * @param string $key Entry name, such as Author or Subject.
     * @return string|null UTF-8 value, null when the entry is missing.
     */
    private static function pdf_info(string $pdf, string $key): ?string {
        // The trailer points to the information dictionary; outline entries also have a /Title.
        preg_match('~/Info (\d+) 0 R~', $pdf, $info);
        preg_match('~\n' . $info[1] . ' 0 obj\n(.*?)\nendobj~s', $pdf, $object);
        if (!preg_match('~/' . $key . ' \(((?:\\\\.|[^\\\\)])*)\)~s', $object[1], $match)) {
            return null;
        }
        return self::decode_pdf_string($match[1]);
    }

    /**
     * Returns the text drawn on the pages, from the decompressed content streams.
     *
     * Unlike searching the raw bytes, this ignores metadata and bookmarks: only what is printed.
     *
     * @param string $pdf PDF bytes.
     * @return string UTF-8 text, with runs of white space collapsed.
     */
    private static function page_text(string $pdf): string {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);
        $lines = [];
        foreach ($streams[1] as $stream) {
            $content = @gzuncompress($stream);
            if ($content === false || !str_contains($content, ' Td [(')) {
                continue;
            }
            preg_match_all('/Td \[\(((?:\\\\.|[^\\\\)])*)\)\] TJ/s', $content, $strings);
            foreach ($strings[1] as $string) {
                $lines[] = self::decode_pdf_string($string);
            }
        }
        return trim(preg_replace('/\s+/u', ' ', implode(' ', $lines)));
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
        $this->assertSame([], $document['attribution']);
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
     * Returns a block configuration with a default attribution and two resource overrides.
     *
     * @return \stdClass
     */
    private function attribution_config(): \stdClass {
        return (object)[
            'authors' => "Ana & Bob\nOrganización «X» 李\nMüller (UPV)",
            'license' => 'custom',
            'customlicense' => 'Custom & "Open"',
            'licenseurl' => 'https://example.org/license?a=1&b=2',
            'cmattribution' => [
                $this->cms['book']->cmid => ['authors' => 'Book Writer', 'license' => 'cc_by_sa'],
                $this->cms['links']->cmid => ['authors' => '', 'license' => ''],
            ],
        ];
    }

    /**
     * Without attribution, PDFs have no author metadata and print no attribution.
     */
    public function test_render_without_attribution(): void {
        $this->setUser($this->users['student']);
        $service = $this->service($this->ids('welcome', 'book'), (object)['selectedcmids' => '1']);
        $document = $service->build_course_document();
        $this->assertSame(['authors' => [], 'license' => '', 'licenseurl' => ''], $document['attribution']);
        foreach ([$service->render($document), $service->render($service->build_module_document($this->ids('book')[0]))] as $pdf) {
            $this->assertNull(self::pdf_info($pdf, 'Author'));
            $this->assertStringNotContainsString(get_string('authorslabel', 'block_pdfexport'), self::page_text($pdf));
            $this->assertStringNotContainsString(get_string('licenselabel', 'block_pdfexport'), self::page_text($pdf));
        }
        $this->assertSame('TECH 101', self::pdf_info($service->render($document), 'Subject'));
    }

    /**
     * A course PDF prints the default on the cover and the resources that differ under their
     * heading, and its metadata lists every author and license.
     */
    public function test_attribution_in_course_pdf(): void {
        $this->setUser($this->users['student']);
        $service = $this->service($this->ids('welcome', 'book', 'links'), $this->attribution_config());
        $document = $service->build_course_document();
        $default = ['authors' => ['Ana & Bob', 'Organización «X» 李', 'Müller (UPV)'], 'license' => 'Custom & "Open"',
            'licenseurl' => 'https://example.org/license?a=1&b=2'];
        $this->assertSame($default, $document['attribution']);
        $items = array_merge(...array_column($document['sections'], 'items'));
        $this->assertSame($default, $items[0]['attribution']);
        $this->assertSame(['authors' => ['Book Writer'], 'license' => 'CC BY-SA 4.0',
            'licenseurl' => 'https://creativecommons.org/licenses/by-sa/4.0/'], $items[1]['attribution']);
        $this->assertSame(['authors' => $default['authors'], 'license' => '', 'licenseurl' => ''], $items[2]['attribution']);

        $pdf = $service->render($document);
        $this->assertSame('Ana & Bob; Organización «X» 李; Müller (UPV); Book Writer', self::pdf_info($pdf, 'Author'));
        $this->assertSame('TECH 101 | Custom & "Open"; CC BY-SA 4.0', self::pdf_info($pdf, 'Subject'));
        $this->assertSame('Introduction to Technology', self::pdf_info($pdf, 'Title'));
        $text = self::page_text($pdf);
        // Cover (default), book (own) and links (no license, spelled out); the welcome page inherits silently.
        $this->assertSame(3, substr_count($text, 'Authors:'));
        $this->assertStringContainsString('Authors: Ana & Bob; Organización «X» 李; Müller (UPV)', $text);
        $this->assertStringContainsString('License: Custom & "Open"', $text);
        $this->assertStringContainsString('Authors: Book Writer License: CC BY-SA 4.0', $text);
        $this->assertStringContainsString('Müller (UPV) License: No license specified', $text);
        // Links are annotations: their URIs are stored as raw (escaped) bytes.
        $this->assertStringContainsString('/URI (https://example.org/license?a=1&b=2)', $pdf);
        $this->assertStringContainsString('/URI (https://creativecommons.org/licenses/by-sa/4.0/)', $pdf);
    }

    /**
     * A resource PDF prints and declares the attribution of that resource only.
     */
    public function test_attribution_in_module_pdf(): void {
        $this->setUser($this->users['student']);
        $service = $this->service($this->ids('welcome', 'book', 'links'), $this->attribution_config());

        $pdf = $service->render($service->build_module_document($this->ids('book')[0]));
        $this->assertSame('Book Writer', self::pdf_info($pdf, 'Author'));
        $this->assertSame('Introduction to Technology | CC BY-SA 4.0', self::pdf_info($pdf, 'Subject'));
        $this->assertStringContainsString('Authors: Book Writer License: CC BY-SA 4.0', self::page_text($pdf));
        $this->assertStringNotContainsString('Custom', self::page_text($pdf));

        $pdf = $service->render($service->build_module_document($this->ids('welcome')[0]));
        $this->assertSame('Ana & Bob; Organización «X» 李; Müller (UPV)', self::pdf_info($pdf, 'Author'));
        $this->assertSame('Introduction to Technology | Custom & "Open"', self::pdf_info($pdf, 'Subject'));
        $this->assertStringContainsString('License: Custom & "Open"', self::page_text($pdf));

        // An explicit "no license" is not replaced by the default, and nothing is invented.
        $pdf = $service->render($service->build_module_document($this->ids('links')[0]));
        $this->assertSame('Introduction to Technology', self::pdf_info($pdf, 'Subject'));
        $this->assertStringContainsString('Müller (UPV)', self::page_text($pdf));
        $this->assertStringNotContainsString('License:', self::page_text($pdf));
    }

    /**
     * Attribution HTML escapes every value and spells out unknown values only when asked.
     */
    public function test_attribution_html(): void {
        $this->assertSame('', pdf_document::get_attribution_html([]));
        $this->assertSame('', pdf_document::get_attribution_html(['authors' => [], 'license' => '', 'licenseurl' => '']));
        $html = pdf_document::get_attribution_html([
            'authors' => ['<b>Eve</b>', 'O\'Brien & Co'],
            'license' => '<script>x</script>',
            'licenseurl' => 'https://example.org/?a=1&b="2"',
        ]);
        $this->assertStringContainsString('&lt;b&gt;Eve&lt;/b&gt;; O&#039;Brien &amp; Co', $html);
        $this->assertStringContainsString('<a href="https://example.org/?a=1&amp;b=&quot;2&quot;">&lt;script&gt;x', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $html = pdf_document::get_attribution_html(['authors' => ['Solo']]);
        $this->assertStringNotContainsString(get_string('licenselabel', 'block_pdfexport'), $html);
        $html = pdf_document::get_attribution_html(['authors' => [], 'license' => 'CC BY 4.0', 'licenseurl' => ''], true);
        $this->assertStringContainsString(get_string('authorsnone', 'block_pdfexport'), $html);
        $this->assertStringContainsString('CC BY 4.0', $html);
        $this->assertStringNotContainsString('<a ', $html);
        $html = pdf_document::get_attribution_html([], true);
        $this->assertStringContainsString(get_string('license_none', 'block_pdfexport'), $html);
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
