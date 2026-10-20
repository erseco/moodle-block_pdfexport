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
use block_pdfexport\local\cover_css;
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
#[CoversClass(cover_css::class)]
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
     * The default attribution is normalised and never infers a license.
     *
     * @param mixed $config Block configuration.
     * @param array $expected Expected attribution.
     */
    #[DataProvider('attribution_provider')]
    public function test_get_attribution($config, array $expected): void {
        $this->assertSame($expected, publication::get_attribution($config));
    }

    /**
     * Default attribution cases.
     *
     * @return array
     */
    public static function attribution_provider(): array {
        $none = ['authors' => [], 'license' => '', 'licenseurl' => ''];
        $custom = ['license' => 'custom', 'customlicense' => 'Custom & "Open"'];
        return [
            'null' => [null, $none],
            'legacy alpha config' => [(object)['selectedcmids' => '1,2'], $none],
            'non-scalar values' => [(object)['authors' => ['A'], 'license' => ['cc_by'], 'licenseurl' => new \stdClass()], $none],
            'authors only' => [
                (object)['authors' => "\n Ana & Bob \r\n\r\nOrganización «X» 李\nAna & Bob\nMüller (UPV)\x07\n"],
                ['authors' => ['Ana & Bob', 'Organización «X» 李', 'Müller (UPV)'], 'license' => '', 'licenseurl' => ''],
            ],
            'unknown license' => [(object)['license' => 'gpl'], $none],
            'inherit is not a default' => [(object)['license' => 'inherit'], $none],
            'url without license' => [(object)['licenseurl' => 'https://example.org/'], $none],
            'stale url on a cc license' => [
                (object)['license' => 'cc_by', 'licenseurl' => 'https://example.org/other', 'customlicense' => 'Old'],
                ['authors' => [], 'license' => 'CC BY 4.0', 'licenseurl' => 'https://creativecommons.org/licenses/by/4.0/'],
            ],
            'custom license' => [
                (object)($custom + ['licenseurl' => ' https://example.org/license?a=1&b=2 ']),
                ['authors' => [], 'license' => 'Custom & "Open"', 'licenseurl' => 'https://example.org/license?a=1&b=2'],
            ],
            'custom license without url' => [
                (object)$custom,
                ['authors' => [], 'license' => 'Custom & "Open"', 'licenseurl' => ''],
            ],
            'javascript url' => [(object)($custom + ['licenseurl' => 'javascript:alert(1)']),
                ['authors' => [], 'license' => 'Custom & "Open"', 'licenseurl' => '']],
            'data url' => [(object)($custom + ['licenseurl' => 'data:text/html,x']),
                ['authors' => [], 'license' => 'Custom & "Open"', 'licenseurl' => '']],
            'ftp url' => [(object)($custom + ['licenseurl' => 'ftp://example.org/terms']),
                ['authors' => [], 'license' => 'Custom & "Open"', 'licenseurl' => '']],
            'relative url' => [(object)($custom + ['licenseurl' => '/terms']),
                ['authors' => [], 'license' => 'Custom & "Open"', 'licenseurl' => '']],
            'custom license without name' => [
                (object)['license' => 'custom', 'customlicense' => ' ', 'licenseurl' => 'https://example.org/'],
                $none,
            ],
        ];
    }

    /**
     * Every Creative Commons license gets its own name and official deed, whatever URL is stored.
     */
    public function test_creative_commons_licenses(): void {
        $this->assertCount(6, publication::CC_LICENSES);
        foreach (publication::CC_LICENSES as $key => $url) {
            $attribution = publication::get_attribution((object)['license' => $key, 'licenseurl' => 'https://example.org/']);
            $this->assertSame(get_string('license_' . $key, 'block_pdfexport'), $attribution['license']);
            $this->assertSame($url, $attribution['licenseurl']);
            $slug = str_replace(['cc_', '_'], ['', '-'], $key);
            $this->assertSame('https://creativecommons.org/licenses/' . $slug . '/4.0/', $url);
            $this->assertStringContainsString(strtoupper($slug), $attribution['license']);
        }
    }

    /**
     * Resource overrides take precedence over the default; empty fields and "inherit" use it,
     * while an explicit "no license" does not.
     */
    public function test_get_cm_attributions(): void {
        $this->assertSame([], publication::get_cm_attributions(null));
        $this->assertSame([], publication::get_cm_attributions((object)['selectedcmids' => '1', 'cmattribution' => 'x']));
        $config = (object)[
            'authors' => "Default Author\nSchool",
            'license' => 'cc_by',
            'cmattribution' => [
                10 => ['authors' => "Other Author\nÉquipe 李", 'license' => 'cc_by_nc_sa'],
                11 => ['authors' => '', 'license' => ''],
                12 => ['authors' => 'Only Authors'],
                13 => ['authors' => '', 'license' => 'custom', 'customlicense' => 'House rules',
                    'licenseurl' => 'https://example.org/rules'],
                14 => ['authors' => '', 'license' => 'inherit'],
                -1 => ['authors' => 'Negative'],
                'x' => ['authors' => 'Garbage'],
                15 => 'not an array',
            ],
        ];
        $cc = ['license' => 'CC BY 4.0', 'licenseurl' => 'https://creativecommons.org/licenses/by/4.0/'];
        $this->assertSame([
            10 => ['authors' => ['Other Author', 'Équipe 李'], 'license' => 'CC BY-NC-SA 4.0',
                'licenseurl' => 'https://creativecommons.org/licenses/by-nc-sa/4.0/'],
            11 => ['authors' => ['Default Author', 'School'], 'license' => '', 'licenseurl' => ''],
            12 => ['authors' => ['Only Authors']] + $cc,
            13 => ['authors' => ['Default Author', 'School'], 'license' => 'House rules',
                'licenseurl' => 'https://example.org/rules'],
        ], publication::get_cm_attributions($config));
        // The default itself is unaffected by the overrides.
        $this->assertSame(['authors' => ['Default Author', 'School']] + $cc, publication::get_attribution($config));
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
        $this->assertSame(['authors' => [], 'license' => '', 'licenseurl' => ''], publication::get_attribution($block->config));
        $this->assertSame([], publication::get_cm_attributions($block->config));
        publication::save($block, [8, 8, 9, -1], [
            'default' => [
                'authors' => "Ada Lovelace\n\nOpen Learning ",
                'license' => 'custom',
                'customlicense' => 'Custom terms',
                'licenseurl' => 'https://example.org/terms',
            ],
            'cms' => [
                8 => ['authors' => '', 'license' => 'cc_by_nd', 'customlicense' => 'Stale', 'licenseurl' => 'https://example.org/'],
                9 => ['authors' => '', 'license' => 'inherit', 'customlicense' => '', 'licenseurl' => ''],
            ],
        ]);
        $this->assertSame([8, 9], publication::get_selected_cmids($block->config));

        $reloaded = publication::get_course_block($record->id, $course->id);
        $this->assertSame('8,9', $reloaded->config->selectedcmids);
        $this->assertSame('Mine', $reloaded->config->title);
        $this->assertSame([
            'authors' => ['Ada Lovelace', 'Open Learning'],
            'license' => 'Custom terms',
            'licenseurl' => 'https://example.org/terms',
        ], publication::get_attribution($reloaded->config));
        // Stored normalised: stale custom values are dropped and pure inheritance is not stored.
        $this->assertSame("Ada Lovelace\nOpen Learning", $reloaded->config->authors);
        $this->assertSame(
            [8 => ['authors' => '', 'license' => 'cc_by_nd', 'customlicense' => '', 'licenseurl' => '']],
            $reloaded->config->cmattribution
        );
        $bynd = ['authors' => ['Ada Lovelace', 'Open Learning'], 'license' => 'CC BY-ND 4.0',
            'licenseurl' => 'https://creativecommons.org/licenses/by-nd/4.0/'];
        $this->assertSame([8 => $bynd], publication::get_cm_attributions($reloaded->config));
        // Saving only a selection keeps the attribution.
        publication::save($reloaded, [9]);
        $this->assertSame('Custom terms', publication::get_attribution($reloaded->config)['license']);
        $this->assertCount(1, publication::get_cm_attributions($reloaded->config));

        $course2 = $this->getDataGenerator()->create_course();
        $record2 = $generator->create_course_block($course2);
        $DB->set_field('block_instances', 'configdata', '', ['id' => $record2->id]);
        $fresh = publication::get_course_block($record2->id, $course2->id);
        $this->assertSame([], publication::get_selected_cmids($fresh->config));
        publication::save($fresh, [1]);
        $this->assertSame([1], publication::get_selected_cmids($fresh->config));
    }

    /**
     * The cover stylesheet is saved as typed, kept by selection-only saves, cleared with a blank
     * field and independent per block; saving it keeps the selection and every other setting.
     */
    public function test_cover_css_configuration(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('block_pdfexport');
        $course = $this->getDataGenerator()->create_course();
        $record = $generator->create_course_block($course, [3, 4]);
        $config = unserialize(base64_decode($record->configdata));
        $config->title = 'Mine';
        $config->authors = 'Ana';
        $config->license = 'cc_by';
        $config->cmattribution = [4 => ['authors' => 'Bob', 'license' => 'cc_by_sa', 'customlicense' => '', 'licenseurl' => '']];
        $DB->set_field('block_instances', 'configdata', base64_encode(serialize($config)), ['id' => $record->id]);
        $block = publication::get_course_block($record->id, $course->id);
        // Legacy configuration: no stylesheet, default cover.
        $this->assertSame('', publication::get_cover_css($block->config));
        $this->assertSame([], publication::get_cover_styles($block->config));

        $css = "/* Brand */\r\n.pdfexport-cover-title {\r\n    color: #123456;\r\n}\r\n";
        publication::save($block, [3, 4], null, $css);
        $reloaded = publication::get_course_block($record->id, $course->id);
        $this->assertSame($css, publication::get_cover_css($reloaded->config));
        $this->assertSame(['title' => ['color' => '#123456']], publication::get_cover_styles($reloaded->config));
        $this->assertSame([3, 4], publication::get_selected_cmids($reloaded->config));
        $this->assertSame('Mine', $reloaded->config->title);
        $this->assertSame('CC BY 4.0', publication::get_attribution($reloaded->config)['license']);
        $this->assertSame(['Bob'], publication::get_cm_attributions($reloaded->config)[4]['authors']);

        // Updating the selection and the attribution keeps the stylesheet.
        publication::save($reloaded, [4], publication::get_settings($reloaded->config));
        $reloaded = publication::get_course_block($record->id, $course->id);
        $this->assertSame([4], publication::get_selected_cmids($reloaded->config));
        $this->assertSame($css, publication::get_cover_css($reloaded->config));

        // A second block keeps its own stylesheet.
        $course2 = $this->getDataGenerator()->create_course();
        $other = publication::get_course_block($generator->create_course_block($course2)->id, $course2->id);
        publication::save($other, [], null, '.pdfexport-cover-date { font-size: 8pt }');
        publication::save($reloaded, [4], null, '.pdfexport-cover-title { color: #654321 }');
        $this->assertSame(['title' => ['color' => '#654321']], publication::get_cover_styles(
            publication::get_course_block($record->id, $course->id)->config
        ));
        $this->assertSame(['date' => ['font-size' => '8pt']], publication::get_cover_styles(
            publication::get_course_block($other->instance->id, $course2->id)->config
        ));

        // A blank field restores the default cover.
        publication::save($reloaded, [4], null, " \n\t");
        $reloaded = publication::get_course_block($record->id, $course->id);
        $this->assertSame('', $reloaded->config->covercss);
        $this->assertSame([], publication::get_cover_styles($reloaded->config));
        $this->assertSame([4], publication::get_selected_cmids($reloaded->config));
    }

    /**
     * Stored stylesheets are parsed again before rendering: tampered values give the default cover.
     */
    public function test_get_cover_styles_untrusted(): void {
        foreach ([null, 'string', (object)[], (object)['covercss' => ['x']], (object)['covercss' => 5]] as $config) {
            $this->assertSame('', publication::get_cover_css($config));
            $this->assertSame([], publication::get_cover_styles($config));
        }
        $tampered = (object)['covercss' => '.pdfexport-cover-title { background-image: url(https://evil.example/a.png) }'];
        $this->assertSame([], publication::get_cover_styles($tampered));
        $this->assertDebuggingCalled();
        $this->assertSame([], publication::get_cover_styles((object)['covercss' => 'body { color: #000 }']));
        $this->assertDebuggingCalled();
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
        $customdata = [
            'sections' => $sections,
            'selected' => [(int)$book->cmid],
            'attribution' => publication::get_settings((object)['authors' => 'A. Author', 'license' => 'cc_by',
                'cmattribution' => [$book->cmid => ['authors' => 'Book Author', 'license' => '']]]),
            'covercss' => '.pdfexport-cover-title { color: #123456 }',
        ];

        $form = new selection_form(null, $customdata);
        $html = $form->render();
        $this->assertStringContainsString('name="cm[' . $page->cmid . ']"', $html);
        $this->assertStringContainsString('name="cm[' . $book->cmid . ']"', $html);
        $this->assertStringContainsString(get_string('support_full', 'block_pdfexport'), $html);
        $this->assertStringContainsString('name="authors"', $html);
        $this->assertStringContainsString('name="license"', $html);
        $this->assertStringContainsString('A. Author', $html);
        $this->assertStringContainsString('name="cmlicense[' . $page->cmid . ']"', $html);
        $this->assertStringContainsString('Book Author', $html);
        $this->assertMatchesRegularExpression(
            '~<textarea[^>]+name="covercss"[^>]*>\s*\.pdfexport-cover-title \{ color: #123456 \}~',
            $html
        );
        $this->assertMatchesRegularExpression('~<option value="cc_by" selected~', $html);
        $this->assertMatchesRegularExpression('~type="checkbox"\s+name="cmoverride\[' . $book->cmid . '\]"[^>]*checked~', $html);
        $this->assertDoesNotMatchRegularExpression(
            '~type="checkbox"\s+name="cmoverride\[' . $page->cmid . '\]"[^>]*checked~',
            $html
        );
        $this->assertMatchesRegularExpression('~<option value="inherit" selected~', $html);
        $this->assertStringNotContainsString('Quiz', $html);
        $this->assertMatchesRegularExpression('~type="checkbox"\s+name="cm\[' . $book->cmid . '\]"[^>]*checked~', $html);
        $this->assertDoesNotMatchRegularExpression('~type="checkbox"\s+name="cm\[' . $page->cmid . '\]"[^>]*checked~', $html);

        selection_form::mock_submit([
            'cm' => [$page->cmid => 1, $book->cmid => 0, 999999 => 1],
            'authors' => "Ana\nBob",
            'license' => 'custom',
            'customlicense' => 'Terms',
            'licenseurl' => 'https://example.org/terms',
            'cmoverride' => [$page->cmid => 1, $book->cmid => 0, 999999 => 1],
            'cmauthors' => [$page->cmid => 'Page Author', $book->cmid => 'Ignored', 999999 => 'Foreign'],
            'cmlicense' => [$page->cmid => '', $book->cmid => 'cc_by', 999999 => 'cc_by'],
            'cmcustomlicense' => [$page->cmid => '', $book->cmid => ''],
            'cmlicenseurl' => [$page->cmid => '', $book->cmid => ''],
            'covercss' => ".pdfexport-cover-date {\r\n  color: #777;\r\n}",
        ]);
        $form = new selection_form(null, $customdata);
        $data = $form->get_data();
        $this->assertSame([(int)$page->cmid], $form->get_selected_cmids($data));
        $this->assertSame([], $form->get_selected_cmids((object)[]));
        // Raw parameter: the stylesheet is kept exactly as typed.
        $this->assertSame(".pdfexport-cover-date {\r\n  color: #777;\r\n}", $data->covercss);
        $this->assertSame([
            'default' => ['authors' => "Ana\nBob", 'license' => 'custom', 'customlicense' => 'Terms',
                'licenseurl' => 'https://example.org/terms'],
            'cms' => [(int)$page->cmid => ['authors' => 'Page Author', 'license' => '', 'customlicense' => '', 'licenseurl' => '']],
        ], $form->get_attribution_settings($data));

        $errors = $form->validation(['license' => 'custom', 'customlicense' => '', 'licenseurl' => 'javascript:alert(1)'], []);
        $this->assertArrayHasKey('customlicense', $errors);
        $this->assertArrayHasKey('licenseurl', $errors);
        // Unused custom fields are not validated: the URL is dropped for other licenses.
        $this->assertSame([], $form->validation(['license' => 'cc_by', 'licenseurl' => 'javascript:alert(1)'], []));
        $errors = $form->validation([
            'license' => '',
            'cmoverride' => [5 => 1, 6 => 0],
            'cmlicense' => [5 => 'custom', 6 => 'custom'],
            'cmcustomlicense' => [5 => '', 6 => ''],
            'cmlicenseurl' => [5 => 'ftp://example.org', 6 => 'ftp://example.org'],
        ], []);
        $this->assertSame(['cmcustomlicense[5]', 'cmlicenseurl[5]'], array_keys($errors));

        // The cover stylesheet is optional and validated with a localised message.
        $this->assertSame([], $form->validation(['license' => '', 'covercss' => ''], []));
        $this->assertSame([], $form->validation(['license' => '', 'covercss' => '.pdfexport-cover { color: #000 }'], []));
        $errors = $form->validation(['license' => '', 'covercss' => '@import "https://evil.example/x.css";'], []);
        $this->assertSame(['covercss' => get_string('covercsscharacters', 'block_pdfexport')], $errors);
        $errors = $form->validation(['license' => '', 'covercss' => '.pdfexport-cover-title { position: absolute }'], []);
        $this->assertSame(['covercss' => get_string('covercssproperty', 'block_pdfexport', 'position')], $errors);

        // Without candidates the attribution and cover style can still be prepared.
        $html = (new selection_form(null, ['sections' => [], 'selected' => []]))->render();
        $this->assertStringContainsString('name="covercss"', $html);
        $this->assertStringContainsString('name="authors"', $html);
        $this->assertStringNotContainsString('name="selectall"', $html);
    }
}
