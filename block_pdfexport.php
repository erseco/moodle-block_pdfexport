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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

class block_pdfexport extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_pdfexport');
    }

    public function applicable_formats() {
        return ['course-view' => true, 'site-index' => false, 'my' => false];
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function get_content() {
        global $OUTPUT;
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = (object)['text' => '', 'footer' => ''];
        $course = $this->page->course;
        if (!$course || empty($course->id) || (int)$course->id === SITEID) {
            return $this->content;
        }
        $context = context_course::instance($course->id);
        $canconfigure = has_capability('block/pdfexport:configure', $context)
            && has_capability('moodle/course:update', $context);
        $canview = has_capability('block/pdfexport:view', $context);
        if (!$canconfigure && !$canview) {
            return $this->content;
        }
        $chosen = array_filter(array_map('intval', explode(',', $this->config->selectedcmids ?? '')));
        $chosen = array_fill_keys($chosen, true);
        $resources = \block_pdfexport\local\resource_detector::get_resources($course->id);
        $list = [];
        foreach ($resources as $resource) {
            if (!isset($chosen[$resource['cmid']]) || !$resource['uservisible']) {
                continue;
            }
            $cm = get_fast_modinfo($course)->get_cm($resource['cmid']);
            $url = $cm->url ? $cm->url->out(false) : null;
            if ($url) {
                $pdfurl = new moodle_url('/blocks/pdfexport/download.php',
                    ['courseid' => $course->id, 'blockid' => $this->instance->id, 'cmid' => $resource['cmid']]);
                $link = html_writer::link($url, format_string($resource['name']),
                    ['title' => get_string('openoriginal', 'block_pdfexport')]);
                $link .= ' — ' . html_writer::link($pdfurl, get_string('downloadpdf', 'block_pdfexport'));
                $list[] = html_writer::tag('li', $link);
            }
        }
        if ($list && $canview) {
            $this->content->text = html_writer::tag('p', get_string('pdfexperimental', 'block_pdfexport'));
            $this->content->text .= html_writer::tag('ul', implode('', $list));
        } else if ($canview) {
            $this->content->text = html_writer::tag('p', get_string('noresources', 'block_pdfexport'));
        }
        if ($canconfigure) {
            $url = new moodle_url('/blocks/pdfexport/manage.php',
                ['courseid' => $course->id, 'blockid' => $this->instance->id]);
            $this->content->footer = html_writer::link($url, get_string('manageresources', 'block_pdfexport'));
        }
        return $this->content;
    }
}
