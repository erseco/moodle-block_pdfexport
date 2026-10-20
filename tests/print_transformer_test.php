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

use block_pdfexport\local\print_transformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the HTML to printable HTML transformation.
 *
 * @package    block_pdfexport
 * @category   test
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(print_transformer::class)]
final class print_transformer_test extends \advanced_testcase {
    /** @var \context_module Context owning the test file area. */
    private \context_module $context;

    /**
     * Creates a page whose content area holds the test images.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->context = \context_module::instance($page->cmid);
    }

    /**
     * Returns PNG bytes of the given size.
     *
     * @param int $width Width.
     * @param int $height Height.
     * @return string
     */
    private static function png(int $width = 20, int $height = 10): string {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        return ob_get_clean();
    }

    /**
     * Stores a file in the page content area.
     *
     * @param string $filename File name.
     * @param string $content Content.
     * @param string $filepath File path.
     * @return \stored_file
     */
    private function store(string $filename, string $content, string $filepath = '/'): \stored_file {
        return get_file_storage()->create_file_from_string(['contextid' => $this->context->id,
            'component' => 'mod_page', 'filearea' => 'content', 'itemid' => 0, 'filepath' => $filepath,
            'filename' => $filename], $content);
    }

    /**
     * Transforms HTML for the page content area.
     *
     * @param print_transformer $transformer Transformer.
     * @param string $html HTML.
     * @return string
     */
    private function transform(print_transformer $transformer, string $html): string {
        return $transformer->transform($html, $this->context, 'mod_page', 'content', 0);
    }

    /**
     * Returns the pluginfile URL of the page content area.
     *
     * @param string $path Path after the item id.
     * @return string
     */
    private function url(string $path): string {
        global $CFG;
        return $CFG->wwwroot . '/pluginfile.php/' . $this->context->id . '/mod_page/content/0/' . $path;
    }

    /**
     * Static content is preserved and content headings are demoted below the structure.
     */
    public function test_static_content(): void {
        $transformer = new print_transformer();
        $html = $this->transform($transformer, '<h1>Título</h1><h3>Sub</h3><p><strong>Bold</strong> and ' .
            '<em>italic</em> ñ € 漢字</p><ol start="3"><li>One</li></ol><ul><li>Two</li></ul><blockquote>Q</blockquote>' .
            '<pre>code</pre><p><code>x</code></p><dl><dt>T</dt><dd>D</dd></dl>');
        $this->assertStringContainsString('<h4 style="font-size:12pt;">Título</h4>', $html);
        $this->assertStringContainsString('<h5 style="font-size:11pt;">Sub</h5>', $html);
        $this->assertStringContainsString('<strong>Bold</strong>', $html);
        $this->assertStringContainsString('<em>italic</em> ñ € 漢字', $html);
        $this->assertStringContainsString('<ol start="3"><li>One</li></ol>', $html);
        $this->assertStringContainsString('<tt>x</tt>', $html);
        $this->assertStringContainsString('<dl><dt>T</dt><dd>D</dd></dl>', $html);
        $this->assertSame([], $transformer->take_warnings());
        $this->assertSame('', $this->transform($transformer, '   '));
    }

    /**
     * Tables get borders, captions are moved before the table and cell spans are bounded.
     */
    public function test_tables(): void {
        $html = $this->transform(new print_transformer(), '<table class="x" style="width:10px"><caption>Plan</caption>' .
            '<tbody><tr><th colspan="2">H</th></tr><tr><td rowspan="99999">A</td><td colspan="abc">B</td></tr></tbody>' .
            '</table>');
        $this->assertStringStartsWith('<p><strong>Plan</strong></p><table border="1" cellpadding="4">', $html);
        $this->assertStringContainsString('<th colspan="2">H</th>', $html);
        $this->assertStringContainsString('<td rowspan="1000">A</td><td colspan="1">B</td>', $html);
        $this->assertStringNotContainsString('tbody', $html);
        $this->assertStringNotContainsString('class=', $html);
        $this->assertStringNotContainsString('style=', $html);
    }

    /**
     * Link schemes are restricted; unsafe links keep only their text.
     *
     * @param string $href Link target.
     * @param string|null $expected Expected href, or null when the link must be removed.
     */
    #[DataProvider('link_provider')]
    public function test_links(string $href, ?string $expected): void {
        global $CFG;
        $html = $this->transform(new print_transformer(), '<p><a href="' . s($href) . '" onclick="x()">Text</a></p>');
        if ($expected === null) {
            $this->assertSame('<p>Text</p>', $html);
        } else {
            $expected = str_replace('WWWROOT', $CFG->wwwroot, $expected);
            $this->assertSame('<p><a href="' . s($expected) . '">Text</a></p>', $html);
        }
    }

    /**
     * Link cases.
     *
     * @return array
     */
    public static function link_provider(): array {
        return [
            'https' => ['https://example.com/a?b=1', 'https://example.com/a?b=1'],
            'http' => ['http://example.com', 'http://example.com'],
            'mailto' => ['mailto:someone@example.com', 'mailto:someone@example.com'],
            'mailto invalid' => ['mailto:not an address', null],
            'site relative' => ['/mod/page/view.php?id=3', 'WWWROOT/mod/page/view.php?id=3'],
            'javascript' => ['javascript:alert(1)', null],
            'javascript mixed case' => [' JaVaScRiPt:alert(1)', null],
            'data' => ['data:text/html;base64,PHNjcmlwdD4=', null],
            'file' => ['file:///etc/passwd', null],
            'protocol relative' => ['//evil.example/x', null],
            'relative' => ['../secret.html', null],
            'anchor' => ['#top', null],
            'empty' => ['', null],
        ];
    }

    /**
     * Scripts, styles and external resource tags are removed; event attributes are stripped.
     */
    public function test_unsafe_markup_removed(): void {
        $transformer = new print_transformer();
        $html = $this->transform($transformer, '<script>alert(1)</script><style>p{}</style>' .
            '<link rel="stylesheet" href="https://evil.example/x.css"><meta http-equiv="refresh" content="0">' .
            '<base href="https://evil.example/"><tcpdf method="AddPage" /><!-- comment -->' .
            '<p onmouseover="x()" style="background:url(https://evil.example/t.png)" class="a" id="b">Safe</p>' .
            '<form action="https://evil.example"><label>Name</label><input name="n"><button>Go</button></form>');
        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringNotContainsString('alert', $html);
        $this->assertStringNotContainsString('tcpdf', $html);
        $this->assertStringNotContainsString('comment', $html);
        $this->assertStringContainsString('<p>Safe</p>', $html);
        $this->assertStringContainsString('Name', $html);
        $this->assertStringContainsString(get_string('interactiveplaceholder', 'block_pdfexport'), $html);
        $this->assertSame([get_string('warning_interactive', 'block_pdfexport')], $transformer->take_warnings());
    }

    /**
     * Malformed HTML is repaired rather than rejected.
     */
    public function test_malformed_html(): void {
        $html = $this->transform(new print_transformer(), '<p>Open <b>bold <i>both</p><div>Next</span>');
        $this->assertStringContainsString('Open', $html);
        $this->assertStringContainsString('Next', $html);
        $this->assertStringNotContainsString('</span>', $html);
    }

    /**
     * Author-hidden content is never revealed; disclosure widgets and tab panes are printed.
     */
    public function test_hidden_and_collapsed_content(): void {
        $transformer = new print_transformer();
        $html = $this->transform($transformer, '<details><summary>Question</summary><p>Visible answer</p></details>' .
            '<div class="tab-content"><div class="tab-pane fade">Second tab</div></div>' .
            '<div class="collapse">Collapsed accordion</div>' .
            '<p hidden>Hidden attribute secret</p><div style="display: none">Display none secret</div>' .
            '<span style="VISIBILITY:hidden">Visibility secret</span>' .
            '<span aria-hidden="true">icon</span>');
        $this->assertStringContainsString('<p><strong>Question</strong></p>', $html);
        $this->assertStringContainsString('Visible answer', $html);
        $this->assertStringContainsString('Second tab', $html);
        $this->assertStringContainsString('Collapsed accordion', $html);
        $this->assertStringNotContainsString('secret', $html);
        $this->assertStringNotContainsString('icon', $html);
        $this->assertSame([get_string('warning_hiddencontent', 'block_pdfexport')], $transformer->take_warnings());
    }

    /**
     * Embedded media and interactive graphics become descriptive placeholders.
     */
    public function test_media(): void {
        $transformer = new print_transformer();
        $html = $this->transform($transformer, '<iframe src="https://www.youtube.com/embed/x" title="Lecture"></iframe>' .
            '<video><source src="https://example.com/v.mp4"></video><audio src="javascript:alert(1)"></audio>' .
            '<object data="/mod/page/x.swf"></object><svg><text>S</text></svg><canvas></canvas>');
        $this->assertStringContainsString('[Embedded media: Lecture] </em><a href="https://www.youtube.com/embed/x">', $html);
        $this->assertStringContainsString('<a href="https://example.com/v.mp4">', $html);
        $this->assertStringContainsString(
            '[Embedded media: audio] </em>' . get_string('mediaunavailable', 'block_pdfexport'),
            $html
        );
        $this->assertStringNotContainsString('javascript', $html);
        $this->assertStringNotContainsString('<svg', $html);
        $this->assertCount(2, $transformer->take_warnings());
    }

    /**
     * Images of the file area are embedded inline, resized to the text column, in subdirectories too.
     */
    public function test_images_embedded(): void {
        $this->store('small.png', self::png(20, 10));
        $this->store('wide image.png', self::png(1200, 100), '/sub dir/');
        $transformer = new print_transformer();
        $html = $this->transform($transformer, '<p><img src="' . $this->url('small.png') . '" alt="Small" ' .
            'height="999" onerror="x()"><img src="' . $this->url('sub%20dir/wide%20image.png') . '"></p>');
        $this->assertStringContainsString(
            '<img src="@' . base64_encode(self::png(20, 10)) . '" width="20" alt="Small">',
            $html
        );
        $this->assertStringContainsString('width="' . print_transformer::MAX_IMAGE_WIDTH . '"', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('pluginfile', $html);
        $this->assertSame([], $transformer->take_warnings());
    }

    /**
     * The slasharguments-off URL form is resolved too.
     */
    public function test_image_query_url(): void {
        global $CFG;
        $this->store('q.png', self::png());
        $src = $CFG->wwwroot . '/pluginfile.php?file=' . rawurlencode('/' . $this->context->id . '/mod_page/content/0/q.png');
        $html = $this->transform(new print_transformer(), '<img src="' . $src . '">');
        $this->assertStringStartsWith('<img src="@', $html);
    }

    /**
     * Images that cannot be embedded are replaced by their description, with a warning.
     *
     * @param string $src Image source (ITEM is replaced by the file area URL prefix).
     * @param string $warning Expected warning identifier.
     */
    #[DataProvider('rejected_image_provider')]
    public function test_images_rejected(string $src, string $warning): void {
        global $CFG;
        $this->store('real.png', self::png());
        $this->store('fake.png', 'not an image');
        $this->store('anim.gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=='));
        $other = $this->getDataGenerator()->create_module('page', ['course' => $this->context->get_course_context()->instanceid]);
        $othercontext = \context_module::instance($other->cmid);
        $src = str_replace(['ITEM/', 'OTHERCTX', 'WWWROOT', 'CTX'], [$this->url(''), (string)$othercontext->id,
            $CFG->wwwroot, (string)$this->context->id], $src);
        $transformer = new print_transformer();
        $html = $this->transform($transformer, '<img src="' . s($src) . '" alt="Diagram">');
        $this->assertSame('<p><em>' . get_string('imagenotincluded', 'block_pdfexport', 'Diagram') . '</em></p>', $html);
        $this->assertSame([get_string($warning, 'block_pdfexport')], $transformer->take_warnings());
    }

    /**
     * Rejected image cases.
     *
     * @return array
     */
    public static function rejected_image_provider(): array {
        return [
            'remote' => ['https://example.com/a.png', 'warning_remoteimage'],
            'missing' => ['ITEM/missing.png', 'warning_imagenotfound'],
            'traversal' => ['ITEM/../../real.png', 'warning_imagenotfound'],
            'encoded traversal' => ['ITEM/%2e%2e/real.png', 'warning_imagenotfound'],
            'double slash' => ['ITEM//real.png', 'warning_imagenotfound'],
            'other context' => ['WWWROOT/pluginfile.php/OTHERCTX/mod_page/content/0/real.png', 'warning_imagenotfound'],
            'other area' => ['WWWROOT/pluginfile.php/CTX/mod_page/intro/0/real.png', 'warning_imagenotfound'],
            'other item' => ['WWWROOT/pluginfile.php/CTX/mod_page/content/5/real.png', 'warning_imagenotfound'],
            'empty query' => ['WWWROOT/pluginfile.php?nofile=1', 'warning_imagenotfound'],
            'relative' => ['real.png', 'warning_imagenotfound'],
            'local path' => ['/etc/passwd', 'warning_imagenotfound'],
            'spoofed png' => ['ITEM/fake.png', 'warning_imageformat'],
            'gif' => ['ITEM/anim.gif', 'warning_imageformat'],
            'svg data uri' => ['data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=', 'warning_imagenotfound'],
            'bad data uri' => ['data:image/png;base64,bm90IGFuIGltYWdl', 'warning_imageformat'],
        ];
    }

    /**
     * Inline PNG data URIs are validated and embedded; images without a description say so.
     */
    public function test_data_uri_and_missing_alt(): void {
        $transformer = new print_transformer();
        $html = $this->transform($transformer, '<img src="data:image/png;base64,' . base64_encode(self::png()) . '">');
        $this->assertStringStartsWith('<img src="@', $html);
        $html = $this->transform($transformer, '<img src="https://example.com/x.png">');
        $this->assertStringContainsString(get_string('imagenodescription', 'block_pdfexport'), $html);
    }

    /**
     * Images in an area without item id segment (intro areas) are resolved.
     */
    public function test_area_without_itemid(): void {
        global $CFG;
        get_file_storage()->create_file_from_string(['contextid' => $this->context->id, 'component' => 'mod_page',
            'filearea' => 'intro', 'itemid' => 0, 'filepath' => '/', 'filename' => 'i.png'], self::png());
        $html = (new print_transformer())->transform('<img src="' . $CFG->wwwroot . '/pluginfile.php/' .
            $this->context->id . '/mod_page/intro/i.png">', $this->context, 'mod_page', 'intro', null);
        $this->assertStringStartsWith('<img src="@', $html);
    }

    /**
     * Size, pixel and count limits are enforced.
     */
    public function test_image_limits(): void {
        $transformer = new print_transformer();
        $reflection = new \ReflectionMethod($transformer, 'load_image');
        $this->assertNull($reflection->invoke($transformer, self::png(), print_transformer::MAX_IMAGE_BYTES + 1));
        $this->assertSame([get_string('warning_imagetoolarge', 'block_pdfexport')], $transformer->take_warnings());

        $this->assertNull($reflection->invoke($transformer, self::png(6000, 5000), 1000));
        $this->assertSame([get_string('warning_imagetoolarge', 'block_pdfexport')], $transformer->take_warnings());

        $count = new \ReflectionProperty($transformer, 'imagecount');
        $count->setValue($transformer, print_transformer::MAX_IMAGES);
        $this->assertNull($reflection->invoke($transformer, self::png(), 100));
        $this->assertSame([get_string('warning_imagelimit', 'block_pdfexport')], $transformer->take_warnings());
    }

    /**
     * Stored files can be embedded directly, falling back to the placeholder.
     */
    public function test_image_html(): void {
        $transformer = new print_transformer();
        $html = $transformer->image_html($this->store('a.png', self::png()), 'Poster <b>');
        $this->assertStringContainsString('alt="Poster &lt;b&gt;"', $html);
        $this->assertStringStartsWith('<img src="@', $html);
        $this->assertSame(
            get_string('imagenotincluded', 'block_pdfexport', 'Bad'),
            $transformer->image_html($this->store('b.png', 'text'), 'Bad')
        );
    }

    /**
     * Warnings are de-duplicated and cleared when taken.
     */
    public function test_warnings(): void {
        $transformer = new print_transformer();
        $transformer->add_warning('warning_media');
        $transformer->add_warning('warning_media');
        $this->assertSame([get_string('warning_media', 'block_pdfexport')], $transformer->take_warnings());
        $this->assertSame([], $transformer->take_warnings());
    }
}
