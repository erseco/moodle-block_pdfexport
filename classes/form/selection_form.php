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

namespace block_pdfexport\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use block_pdfexport\local\adapters\base_adapter;

/**
 * Selection of the published resources, grouped by course section.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class selection_form extends \moodleform {
    /**
     * Defines one checkbox per exportable module, under a header per section.
     */
    protected function definition() {
        global $PAGE;
        $mform = $this->_form;
        $selected = array_fill_keys($this->_customdata['selected'], true);
        $defaults = [];
        // Select all / none without a page reload, through core/checkbox-toggleall: one toggler
        // for the whole course and one per section (its toggle group is a subgroup of the first).
        $PAGE->requires->js_call_amd('core/checkbox-toggleall', 'init');
        $mform->addElement(
            'advcheckbox',
            'selectall',
            '',
            get_string('selectall'),
            $this->toggler_attributes('pdfexport')
        );
        foreach ($this->_customdata['sections'] as $section) {
            $group = 'pdfexport section' . $section['number'];
            $mform->addElement('header', 'section' . $section['number'], s($section['name']));
            $mform->setExpanded('section' . $section['number']);
            $mform->addElement(
                'advcheckbox',
                'selectsection' . $section['number'],
                '',
                get_string('selectall'),
                $this->toggler_attributes($group)
            );
            foreach ($section['cms'] as $cm) {
                $support = base_adapter::for_module($cm->modname)->get_support_level();
                $description = $cm->get_module_type_name() . ' · ' . get_string('support_' . $support, 'block_pdfexport');
                $mform->addElement(
                    'advcheckbox',
                    'cm[' . $cm->id . ']',
                    s($cm->get_formatted_name(['escape' => false])),
                    s($description),
                    ['data-action' => 'toggle', 'data-toggle' => 'target', 'data-togglegroup' => $group],
                    [0, 1]
                );
                $defaults[$cm->id] = isset($selected[$cm->id]) ? 1 : 0;
            }
        }
        // Element names are "cm[<id>]", so QuickForm reads their defaults from a nested array.
        $mform->setDefaults(['cm' => $defaults]);
        $this->add_action_buttons();
    }

    /**
     * Returns the attributes of a "select all" toggler checkbox.
     *
     * @param string $group Toggle group.
     * @return array
     */
    private function toggler_attributes(string $group): array {
        return ['data-action' => 'toggle', 'data-toggle' => 'toggler', 'data-togglegroup' => $group,
            'data-toggle-selectall' => get_string('selectall'), 'data-toggle-deselectall' => get_string('deselectall')];
    }

    /**
     * Returns the selected ids that belong to the listed modules; anything else is ignored.
     *
     * @param \stdClass $data Submitted data.
     * @return int[]
     */
    public function get_selected_cmids(\stdClass $data): array {
        $eligible = [];
        foreach ($this->_customdata['sections'] as $section) {
            foreach ($section['cms'] as $cm) {
                $eligible[(int)$cm->id] = true;
            }
        }
        $ids = [];
        foreach ((array)($data->cm ?? []) as $id => $checked) {
            if ($checked && isset($eligible[(int)$id])) {
                $ids[] = (int)$id;
            }
        }
        return $ids;
    }
}
