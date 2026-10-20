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
 * Behat steps for block_pdfexport.
 *
 * @package    block_pdfexport
 * @category   test
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode;

/**
 * Behat steps for block_pdfexport.
 *
 * @package    block_pdfexport
 * @category   test
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_block_pdfexport extends behat_base {
    /**
     * Adds a PDF Export block to a course and publishes the listed activities.
     *
     * @Given /^the PDF Export block of "(?P<shortname_string>[^"]*)" publishes:$/
     * @param string $shortname Course short name.
     * @param TableNode $activities Single-column table of activity names.
     */
    public function the_pdf_export_block_publishes(string $shortname, TableNode $activities): void {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
        $cmids = [];
        foreach ($activities->getRows() as [$name]) {
            $cmids[] = $this->get_cmid($course, $name);
        }
        $context = context_course::instance($course->id);
        $record = $DB->get_record('block_instances', ['blockname' => 'pdfexport', 'parentcontextid' => $context->id]);
        if (!$record) {
            $record = testing_util::get_data_generator()->create_block('pdfexport', ['parentcontextid' => $context->id,
                'pagetypepattern' => 'course-view-*', 'defaultregion' => 'side-pre']);
        }
        \block_pdfexport\local\publication::save(block_instance('pdfexport', $record), $cmids);
    }

    /**
     * Requests a PDF Export page with the current session and checks that it is refused.
     *
     * The request is made outside the browser (like core download steps), so the expected
     * error page does not trip the Behat exception detector.
     *
     * @Then /^the PDF Export (download|management) page of "([^"]*)" in "([^"]*)" should be refused with "([^"]*)"$/
     * @param string $page "download" or "management".
     * @param string $target Activity name, or "the course".
     * @param string $shortname Course short name.
     * @param string $message Text the response must contain.
     */
    public function the_pdf_export_page_should_be_refused(
        string $page,
        string $target,
        string $shortname,
        string $message
    ): void {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
        $blockid = $DB->get_field('block_instances', 'id', ['blockname' => 'pdfexport',
            'parentcontextid' => context_course::instance($course->id)->id], MUST_EXIST);
        $params = ['courseid' => $course->id, 'blockid' => $blockid];
        if ($target !== 'the course') {
            $params['cmid'] = $this->get_cmid($course, $target);
        }
        $url = new moodle_url('/blocks/pdfexport/' . ($page === 'download' ? 'download.php' : 'manage.php'), $params);
        $session = $this->getSession()->getCookie('MoodleSession');
        // Error pages are not HTTP 200, so ask for the full response instead of the body only.
        $response = (string)download_file_content(
            $url->out(false),
            ['Cookie' => 'MoodleSession=' . $session],
            null,
            true
        )->results;
        if (str_starts_with($response, '%PDF')) {
            throw new \Behat\Mink\Exception\ExpectationException('A PDF was served for ' . $url, $this->getSession());
        }
        if (!str_contains(html_entity_decode($response, ENT_QUOTES), $message)) {
            throw new \Behat\Mink\Exception\ExpectationException(
                '"' . $message . '" not found in the response of ' . $url,
                $this->getSession()
            );
        }
    }

    /**
     * Returns the course module id of an activity by name.
     *
     * @param stdClass $course Course.
     * @param string $name Activity name.
     * @return int
     */
    private function get_cmid(stdClass $course, string $name): int {
        foreach (get_fast_modinfo($course, -1)->get_cms() as $cm) {
            if ($cm->name === $name) {
                return (int)$cm->id;
            }
        }
        throw new coding_exception('Activity "' . $name . '" not found in ' . $course->shortname);
    }
}
