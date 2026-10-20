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
 * Lets authorised teachers choose which course resources the block publishes.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use block_pdfexport\form\selection_form;
use block_pdfexport\local\publication;
use block_pdfexport\local\resource_detector;

$courseid = required_param('courseid', PARAM_INT);
$blockid = required_param('blockid', PARAM_INT);
$course = get_course($courseid);
require_login($course, false);
$context = context_course::instance($course->id);
require_capability('block/pdfexport:configure', $context);
require_capability('moodle/course:update', $context);
$block = publication::get_course_block($blockid, $course->id);

$url = new moodle_url('/blocks/pdfexport/manage.php', ['courseid' => $course->id, 'blockid' => $blockid]);
$courseurl = course_get_url($course);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('manageresources', 'block_pdfexport'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

$sections = resource_detector::get_sections(get_fast_modinfo($course), false);
$form = new selection_form($url, ['sections' => $sections,
    'selected' => publication::get_selected_cmids($block->config),
    'attribution' => publication::get_settings($block->config)]);
if ($form->is_cancelled()) {
    redirect($courseurl);
} else if ($data = $form->get_data()) {
    publication::save($block, $form->get_selected_cmids($data), $form->get_attribution_settings($data));
    redirect($courseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageresources', 'block_pdfexport'));
echo html_writer::tag('p', get_string('selectiondescription', 'block_pdfexport'));
if ($sections) {
    $form->display();
    if (publication::get_selected_cmids($block->config)) {
        echo html_writer::link(new moodle_url(
            '/blocks/pdfexport/download.php',
            ['courseid' => $course->id, 'blockid' => $blockid]
        ), get_string('coursepdfpreview', 'block_pdfexport'));
    }
} else {
    echo $OUTPUT->notification(get_string('nocandidates', 'block_pdfexport'), 'info');
}
echo $OUTPUT->footer();
