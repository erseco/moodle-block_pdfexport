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
 * Downloads the published course content, or one published resource, as PDF.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use block_pdfexport\local\export_service;
use block_pdfexport\local\publication;

$courseid = required_param('courseid', PARAM_INT);
$blockid = required_param('blockid', PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$course = get_course($courseid);
require_login($course, false);
require_capability('block/pdfexport:view', context_course::instance($course->id));
$block = publication::get_course_block($blockid, $course->id);

core_php_time_limit::raise(300);
raise_memory_limit(MEMORY_HUGE);

$service = new export_service($course, publication::get_selected_cmids($block->config));
$document = $cmid ? $service->build_module_document($cmid) : $service->build_course_document();
// The whole PDF is produced before any header is sent, so errors never yield a corrupt download.
$content = $service->render($document);
send_file($content, $service->get_filename($document), 0, 0, true, true, 'application/pdf');
