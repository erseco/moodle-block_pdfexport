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
require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$blockid = required_param('blockid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('block/pdfexport:configure', $context);
require_capability('moodle/course:update', $context);
$block = $DB->get_record('block_instances', ['id' => $blockid, 'blockname' => 'pdfexport'], '*', MUST_EXIST);
$blockcontext = context_block::instance($blockid);
if ((int)$blockcontext->get_parent_context()->id !== (int)$context->id ||
        $block->pagetypepattern !== 'course-view-*') {
    throw new moodle_exception('invalidblock', 'block_pdfexport');
}
$PAGE->set_url(new moodle_url('/blocks/pdfexport/manage.php', ['courseid' => $courseid, 'blockid' => $blockid]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('manageresources', 'block_pdfexport'));
$PAGE->set_heading(format_string($course->fullname));

$selected = [];
if (!empty($block->configdata)) {
    $config = @unserialize(base64_decode($block->configdata), ['allowed_classes' => ['stdClass']]);
    if (is_object($config) && isset($config->selectedcmids)) {
        $selected = array_fill_keys(array_filter(array_map('intval', explode(',', $config->selectedcmids))), true);
    }
}
$resources = \block_pdfexport\local\resource_detector::get_resources($courseid);
$eligible = [];
foreach ($resources as $resource) {
    if ($resource['supported']) {
        $eligible[$resource['cmid']] = $resource;
    }
}
if (data_submitted() && confirm_sesskey()) {
    $requested = optional_param_array('selected', [], PARAM_INT);
    $newselection = [];
    foreach ($requested as $id) {
        $id = (int)$id;
        if (isset($eligible[$id])) {
            $newselection[$id] = true;
        }
    }
    // Preserve any other block config fields.
    if (!isset($config) || !is_object($config)) {
        $config = new stdClass();
    }
    $config->selectedcmids = implode(',', array_keys($newselection));
    $DB->set_field('block_instances', 'configdata', base64_encode(serialize($config)), ['id' => $blockid]);
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]),
        get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageresources', 'block_pdfexport'));
echo html_writer::tag('p', get_string('selectiondescription', 'block_pdfexport'));
echo html_writer::start_tag('form', ['action' => $PAGE->url, 'method' => 'post']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
if (!$resources) {
    echo html_writer::tag('p', get_string('nocandidates', 'block_pdfexport'));
}
foreach ($resources as $resource) {
    $id = $resource['cmid'];
    $label = format_string($resource['name']) . ' (' . s($resource['modname']) . ')';
    if ($resource['supported']) {
        $input = html_writer::empty_tag('input', [
            'type' => 'checkbox', 'id' => 'cm' . $id,
            'name' => 'selected[]', 'value' => $id,
            'checked' => isset($selected[$id]) ? 'checked' : null,
        ]);
        echo html_writer::div($input . ' ' . html_writer::tag('label', $label, ['for' => 'cm' . $id]));
    } else {
        echo html_writer::div($label . ' — ' . get_string('pendinginspection', 'block_pdfexport'));
    }
}
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary mt-3',
    'value' => get_string('savechanges')]);
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
