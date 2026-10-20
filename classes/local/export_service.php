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

use block_pdfexport\local\adapters\base_adapter;

/**
 * Single export pipeline for both the course PDF and the individual resource PDFs.
 *
 * The service works for the current user: it rebuilds the list of published modules from the
 * user's own modinfo on every call, so permissions are checked at export time and never taken
 * from request parameters. It first builds a document model (sections, items, parts and
 * warnings) and then renders it with {@see pdf_document}.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class export_service {
    /** @var int Maximum number of resources in a single PDF. */
    public const MAX_RESOURCES = 300;

    /** @var \stdClass Course record. */
    private \stdClass $course;

    /** @var int[] Published course module ids (block configuration). */
    private array $selected;

    /** @var print_transformer Transformer shared by all items of an export. */
    private print_transformer $transformer;

    /** @var int Maximum number of resources in a single PDF. */
    private int $maxresources;

    /**
     * Constructor.
     *
     * @param \stdClass $course Course record.
     * @param int[] $selected Published course module ids.
     * @param print_transformer|null $transformer Transformer (injectable for tests).
     * @param int $maxresources Maximum number of resources in a course PDF.
     */
    public function __construct(
        \stdClass $course,
        array $selected,
        ?print_transformer $transformer = null,
        int $maxresources = self::MAX_RESOURCES
    ) {
        $this->course = $course;
        $this->selected = $selected;
        $this->transformer = $transformer ?? new print_transformer();
        $this->maxresources = $maxresources;
    }

    /**
     * Returns the published modules the current user can export, grouped by section.
     *
     * @return array[] See resource_detector::get_sections().
     */
    public function get_available_sections(): array {
        // Defence in depth: the entry points already call require_login(), but the service must
        // never export a course the user cannot enter (enrolment, guest access) or view through the block.
        $context = \context_course::instance($this->course->id);
        if (
            !$this->selected || !can_access_course($this->course) ||
                !has_capability('block/pdfexport:view', $context)
        ) {
            return [];
        }
        return resource_detector::get_sections(get_fast_modinfo($this->course), true, $this->selected);
    }

    /**
     * Builds the document model of the whole published selection.
     *
     * @return array Document model.
     * @throws \moodle_exception When nothing can be exported or the selection is too large.
     */
    public function build_course_document(): array {
        $sections = $this->get_available_sections();
        $count = array_sum(array_map(fn(array $section): int => count($section['cms']), $sections));
        if ($count === 0) {
            throw new \moodle_exception('nothingtoexport', 'block_pdfexport');
        }
        if ($count > $this->maxresources) {
            throw new \moodle_exception(
                'toomanyresources',
                'block_pdfexport',
                '',
                ['count' => $count, 'max' => $this->maxresources]
            );
        }
        $context = \context_course::instance($this->course->id);
        $summary = file_rewrite_pluginfile_urls(
            (string)$this->course->summary,
            'pluginfile.php',
            $context->id,
            'course',
            'summary',
            null
        );
        $summary = format_text($summary, (int)$this->course->summaryformat, ['context' => $context]);
        $document = [
            'type' => 'course',
            'title' => format_string($this->course->fullname, true, ['context' => $context, 'escape' => false]),
            'subtitle' => format_string($this->course->shortname, true, ['context' => $context, 'escape' => false]),
            'summary' => $this->transformer->transform($summary, $context, 'course', 'summary', null),
            'sections' => [],
            'warnings' => [],
        ];
        $this->transformer->take_warnings();
        foreach ($sections as $index => $section) {
            $number = $index + 1;
            $items = [];
            foreach ($section['cms'] as $position => $cm) {
                $items[] = $this->build_item($cm, $number . '.' . ($position + 1) . ' ');
            }
            $document['sections'][] = ['title' => $number . '. ' . $section['name'], 'items' => $items];
        }
        return $this->collect_warnings($document);
    }

    /**
     * Builds the document model of one published module.
     *
     * @param int $cmid Course module id from the request.
     * @return array Document model.
     * @throws \moodle_exception When the module is not published or not available to the user.
     */
    public function build_module_document(int $cmid): array {
        foreach ($this->get_available_sections() as $section) {
            foreach ($section['cms'] as $cm) {
                if ((int)$cm->id === $cmid) {
                    $item = $this->build_item($cm, '');
                    return $this->collect_warnings([
                        'type' => 'module',
                        'title' => $item['title'],
                        'subtitle' => format_string(
                            $this->course->fullname,
                            true,
                            ['context' => \context_course::instance($this->course->id), 'escape' => false]
                        ),
                        'summary' => '',
                        'sections' => [['title' => null, 'items' => [$item]]],
                        'warnings' => [],
                    ]);
                }
            }
        }
        // Same answer for unknown, unpublished, hidden and restricted modules: nothing is disclosed.
        throw new \moodle_exception('notpublished', 'block_pdfexport');
    }

    /**
     * Renders a document model as PDF.
     *
     * @param array $document Model from build_course_document() or build_module_document().
     * @return string PDF bytes.
     */
    public function render(array $document): string {
        $pdf = new pdf_document($document['title'], $document['subtitle']);
        $course = $document['type'] === 'course';
        if ($course) {
            $pdf->add_cover(
                $document['title'],
                $document['subtitle'],
                $document['summary'],
                get_string('generatedon', 'block_pdfexport', userdate(time()))
            );
        }
        foreach ($document['sections'] as $section) {
            if ($section['title'] !== null) {
                $pdf->add_heading($section['title'], 0, true);
            }
            foreach ($section['items'] as $item) {
                $pdf->add_heading($item['title'], $course ? 1 : 0);
                foreach ($item['parts'] as $part) {
                    if ($part['title'] !== null) {
                        $pdf->add_heading($part['title'], min(3, $part['level'] + ($course ? 1 : 0)));
                    }
                    $pdf->add_html($part['html']);
                }
            }
        }
        if ($document['warnings']) {
            $pdf->add_heading(get_string('conversionwarnings', 'block_pdfexport'), 0, $course);
            $list = '';
            foreach ($document['warnings'] as $warning) {
                $list .= '<li>' . s($warning) . '</li>';
            }
            $pdf->add_html('<ul>' . $list . '</ul>');
        }
        if ($course) {
            $pdf->add_toc(get_string('contents', 'block_pdfexport'));
        }
        return $pdf->get_content();
    }

    /**
     * Returns a predictable, safe download file name for a document.
     *
     * @param array $document Document model.
     * @return string File name ending in .pdf.
     */
    public function get_filename(array $document): string {
        $name = $this->course->shortname;
        if ($document['type'] === 'module') {
            $name .= '-' . $document['title'];
        }
        $name = preg_replace('/[\s_]+/u', '-', trim(clean_filename(strip_tags($name))));
        $name = trim(preg_replace('/-+/', '-', $name), '-.');
        return shorten_filename(($name !== '' ? $name : 'course') . '.pdf');
    }

    /**
     * Converts one module into a document item.
     *
     * A failure in one module is reported as a warning instead of aborting the whole export.
     *
     * @param \cm_info $cm Accessible course module.
     * @param string $prefix Numbering prefix for the title.
     * @return array Item model.
     */
    private function build_item(\cm_info $cm, string $prefix): array {
        $adapter = base_adapter::for_module($cm->modname);
        try {
            $parts = $adapter->export($cm, $this->transformer);
        } catch (\moodle_exception $e) {
            debugging('block_pdfexport: cannot export cm ' . $cm->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            $this->transformer->add_warning('exportfailed');
            $parts = [['title' => null, 'level' => 0,
                'html' => '<p><em>' . s(get_string('exportfailed', 'block_pdfexport')) . '</em></p>']];
        }
        return [
            'cmid' => (int)$cm->id,
            'modname' => $cm->modname,
            'title' => $prefix . $cm->get_formatted_name(['escape' => false]),
            'support' => $adapter->get_support_level(),
            'parts' => $parts,
            'warnings' => $this->transformer->take_warnings(),
        ];
    }

    /**
     * Copies item warnings to the document, prefixed with the item title.
     *
     * @param array $document Document model.
     * @return array Document model with warnings.
     */
    private function collect_warnings(array $document): array {
        foreach ($document['sections'] as $section) {
            foreach ($section['items'] as $item) {
                foreach ($item['warnings'] as $warning) {
                    $document['warnings'][] = $item['title'] . ': ' . $warning;
                }
            }
        }
        return $document;
    }
}
