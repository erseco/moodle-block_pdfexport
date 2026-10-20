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

/**
 * PDF Export block: lists the published course resources and links to their PDF exports.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_pdfexport\local\adapters\base_adapter;
use block_pdfexport\local\export_service;
use block_pdfexport\local\publication;

/**
 * PDF Export block class.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_pdfexport extends block_base {
    /**
     * Initialises the block title.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_pdfexport');
    }

    /**
     * The block is only meaningful on course pages.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['course-view' => true, 'site-index' => false, 'my' => false];
    }

    /**
     * One block per course is enough.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Builds the block content for the current user.
     *
     * @return stdClass
     */
    public function get_content() {
        global $OUTPUT;
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = (object)['text' => '', 'footer' => ''];
        $course = $this->page->course;
        if (empty($course->id) || (int)$course->id === SITEID) {
            return $this->content;
        }
        $context = context_course::instance($course->id);
        $canconfigure = has_capability('block/pdfexport:configure', $context)
            && has_capability('moodle/course:update', $context);
        if (!has_capability('block/pdfexport:view', $context) && !$canconfigure) {
            return $this->content;
        }
        $service = new export_service($course, publication::get_selected_cmids($this->config));
        $params = ['courseid' => $course->id, 'blockid' => $this->instance->id];
        $sections = [];
        foreach ($service->get_available_sections() as $section) {
            $items = [];
            foreach ($section['cms'] as $cm) {
                $support = base_adapter::for_module($cm->modname)->get_support_level();
                $name = $cm->get_formatted_name(['escape' => false]);
                $items[] = [
                    'name' => $name,
                    'pdflabel' => get_string('downloadpdfof', 'block_pdfexport', $name),
                    'url' => $cm->url ? $cm->url->out(false) : null,
                    'iconurl' => $cm->get_icon_url()->out(false),
                    'pdfurl' => $support === base_adapter::SUPPORT_REFERENCE ? null :
                        (new moodle_url('/blocks/pdfexport/download.php', $params + ['cmid' => $cm->id]))->out(false),
                    'support' => get_string('support_' . $support, 'block_pdfexport'),
                    'isreference' => $support === base_adapter::SUPPORT_REFERENCE,
                ];
            }
            $sections[] = ['name' => $section['name'], 'items' => $items];
        }
        $this->content->text = $OUTPUT->render_from_template('block_pdfexport/content', [
            'hassections' => !empty($sections),
            'sections' => $sections,
            'coursepdfurl' => (new moodle_url('/blocks/pdfexport/download.php', $params))->out(false),
        ]);
        if ($canconfigure) {
            $this->content->footer = html_writer::link(
                new moodle_url('/blocks/pdfexport/manage.php', $params),
                get_string('manageresources', 'block_pdfexport')
            );
        }
        return $this->content;
    }
}
