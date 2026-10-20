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
 * Development only: creates the "PDF Export Demo Course" in a local Moodle (idempotent).
 *
 * Not shipped in the release ZIP. Run from the Moodle root:
 * php public/blocks/pdfexport/scripts/setup_demo.php
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

if ($DB->record_exists('course', ['shortname' => 'PDFDEMO'])) {
    mtrace('PDFDEMO already exists.');
    exit(0);
}

$CFG->noemailever = true;
\core\session\manager::set_user(get_admin());
$generator = testing_util::get_data_generator();

$category = $generator->create_category(['name' => 'PDF Export Demo']);
$course = $generator->create_course([
    'fullname' => 'PDF Export Demo Course',
    'shortname' => 'PDFDEMO',
    'category' => $category->id,
    'numsections' => 3,
    'summary' => '<p>Demonstration course for the <strong>PDF Export</strong> block. ' .
        'Curso de demostración: acentos, eñes y símbolos (€, ü, ç) se exportan correctamente.</p>',
    'summaryformat' => FORMAT_HTML,
]);
foreach ([1 => 'Introduction', 2 => 'Learning resources', 3 => 'Activities'] as $num => $name) {
    course_update_section(
        $course,
        $DB->get_record('course_sections', ['course' => $course->id, 'section' => $num]),
        ['name' => $name]
    );
}

$teacher = $generator->create_user(['username' => 'teacher_demo', 'password' => 'Demo!2026',
    'firstname' => 'Teacher', 'lastname' => 'Demo', 'email' => 'teacher_demo@example.com']);
$student = $generator->create_user(['username' => 'student_demo', 'password' => 'Demo!2026',
    'firstname' => 'Student', 'lastname' => 'Demo', 'email' => 'student_demo@example.com']);
$generator->enrol_user($teacher->id, $course->id, 'editingteacher');
$generator->enrol_user($student->id, $course->id, 'student');

// A real PNG drawn with GD, used by the page and the file resource.
$image = imagecreatetruecolor(640, 300);
imagefill($image, 0, 0, imagecolorallocate($image, 15, 76, 129));
imagefilledellipse($image, 320, 150, 360, 200, imagecolorallocate($image, 255, 196, 0));
imagestring($image, 5, 255, 142, 'PDF Export demo', imagecolorallocate($image, 20, 20, 20));
ob_start();
imagepng($image);
$png = ob_get_clean();

$fs = get_file_storage();
$page = $generator->create_module('page', [
    'course' => $course->id, 'section' => 1, 'name' => 'Welcome / Bienvenida',
    'content' => '<h2>Welcome</h2><p>This page is exported with its <em>formatting</em>, tables and images.</p>' .
        '<p><img src="@@PLUGINFILE@@/diagram.png" alt="Course diagram"></p>' .
        '<table><caption>Course plan</caption><tr><th>Week</th><th>Topic</th></tr>' .
        '<tr><td>1</td><td>Introducción y bienvenida</td></tr><tr><td>2</td><td>Recursos de aprendizaje</td></tr></table>' .
        '<details><summary>What is included in the PDF?</summary><p>Pages, books, text and media areas, ' .
        'images stored in Moodle, and links to everything else.</p></details>' .
        '<p>More information: <a href="https://moodle.org">moodle.org</a>.</p>',
    'contentformat' => FORMAT_HTML,
]);
$fs->create_file_from_string(['contextid' => context_module::instance($page->cmid)->id, 'component' => 'mod_page',
    'filearea' => 'content', 'itemid' => 0, 'filepath' => '/', 'filename' => 'diagram.png'], $png);

$book = $generator->create_module('book', ['course' => $course->id, 'section' => 1, 'name' => 'Introduction book']);
$bookgenerator = $generator->get_plugin_generator('mod_book');
$bookgenerator->create_chapter(['bookid' => $book->id, 'pagenum' => 1, 'title' => 'Chapter 1: Getting started',
    'content' => '<p>Read the welcome page first.</p><ul><li>Explore the sections</li><li>Download the PDF</li></ul>']);
$bookgenerator->create_chapter(['bookid' => $book->id, 'pagenum' => 2, 'subchapter' => 1,
    'title' => 'Subchapter 1.1: Navigation', 'content' => '<p>Use the course index to move between sections.</p>']);
$bookgenerator->create_chapter(['bookid' => $book->id, 'pagenum' => 3, 'title' => 'Chapter 2: Capítulo en español',
    'content' => '<p>El PDF conserva tildes, eñes y la estructura de capítulos.</p>']);
$bookgenerator->create_chapter(['bookid' => $book->id, 'pagenum' => 4, 'hidden' => 1,
    'title' => 'Teacher notes (hidden chapter)', 'content' => '<p>Students never see this chapter.</p>']);

$label = $generator->create_module('label', ['course' => $course->id, 'section' => 2,
    'intro' => '<p><strong>Tip:</strong> the course PDF contains every published resource in course order.</p>']);
$resource = $generator->create_module('resource', ['course' => $course->id, 'section' => 2,
    'name' => 'Course poster (image file)', 'files' => file_get_unused_draft_itemid()]);
$resourcecontext = context_module::instance($resource->cmid);
$fs->delete_area_files($resourcecontext->id, 'mod_resource', 'content');
$fs->create_file_from_string(['contextid' => $resourcecontext->id, 'component' => 'mod_resource',
    'filearea' => 'content', 'itemid' => 0, 'filepath' => '/', 'filename' => 'poster.png'], $png);
$folder = $generator->create_module('folder', ['course' => $course->id, 'section' => 2, 'name' => 'Course materials']);
$foldercontext = context_module::instance($folder->cmid);
foreach (['syllabus.txt' => 'Syllabus', 'exercises/week1.txt' => 'Week 1 exercises'] as $path => $text) {
    $fs->create_file_from_string(['contextid' => $foldercontext->id, 'component' => 'mod_folder',
        'filearea' => 'content', 'itemid' => 0, 'filepath' => '/' . (dirname($path) === '.' ? '' : dirname($path) . '/'),
        'filename' => basename($path)], $text);
}
$url = $generator->create_module('url', ['course' => $course->id, 'section' => 2, 'name' => 'External references',
    'externalurl' => 'https://moodledev.io/docs', 'intro' => '<p>Moodle developer documentation.</p>']);
$quiz = $generator->create_module('quiz', ['course' => $course->id, 'section' => 3, 'name' => 'Practice quiz']);
$h5p = $generator->create_module('h5pactivity', ['course' => $course->id, 'section' => 3,
    'name' => 'Interactive H5P activity', 'intro' => '<p>An interactive activity, available only in Moodle.</p>']);
// Optional: an eXeLearning package when mod_exelearning is installed (`make exelearning`).
$exelearning = null;
if (core_component::get_component_directory('mod_exelearning')) {
    $exelearning = $generator->create_module('exelearning', ['course' => $course->id, 'section' => 3,
        'name' => 'eXeLearning activity', 'intro' => '<p>Text pages are exported; interactive iDevices are only named.</p>',
        'packagefilepath' => __DIR__ . '/../tests/fixtures/actividad-evaluable.elpx']);
}
$hidden = $generator->create_module('page', ['course' => $course->id, 'section' => 3, 'name' => 'Draft answers (hidden)',
    'content' => '<p>Not visible to students.</p>', 'visible' => 0]);

// Add the block to the course page and publish a selection (the quiz is never exportable).
$record = $generator->create_block('pdfexport', ['parentcontextid' => context_course::instance($course->id)->id,
    'pagetypepattern' => 'course-view-*', 'defaultregion' => 'side-pre', 'defaultweight' => -10]);
\block_pdfexport\local\publication::save(block_instance('pdfexport', $record), [$page->cmid, $book->cmid,
    $label->cmid, $resource->cmid, $folder->cmid, $url->cmid, $h5p->cmid, $hidden->cmid,
    ...($exelearning ? [$exelearning->cmid] : [])]);

// Screenshot-friendly defaults for the demo users: block drawer open, course index closed, no user tours.
foreach ([$teacher, $student] as $user) {
    set_user_preference('drawer-open-block', 1, $user);
    set_user_preference('drawer-open-index', 0, $user);
}
$DB->set_field('tool_usertours_tours', 'enabled', 0);
\tool_usertours\cache::notify_tour_change();

mtrace('Created PDFDEMO (course id ' . $course->id . '). Users: teacher_demo / student_demo, password Demo!2026.');
