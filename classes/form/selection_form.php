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
use block_pdfexport\local\cover_css;
use block_pdfexport\local\publication;

/**
 * Selection of the published resources, grouped by course section.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class selection_form extends \moodleform {
    /**
     * Defines one checkbox per exportable module, under a header per section, each with an
     * optional attribution override, then the default attribution and the cover stylesheet.
     */
    protected function definition() {
        global $PAGE;
        $mform = $this->_form;
        $selected = array_fill_keys($this->_customdata['selected'], true);
        $settings = $this->_customdata['attribution'] ?? publication::get_settings(null);
        $defaults = [];
        $attributiondefaults = $settings['default'];
        // Select all / none without a page reload, through core/checkbox-toggleall: one toggler
        // for the whole course and one per section (its toggle group is a subgroup of the first).
        if ($this->_customdata['sections']) {
            $PAGE->requires->js_call_amd('core/checkbox-toggleall', 'init');
            $mform->addElement(
                'advcheckbox',
                'selectall',
                '',
                get_string('selectall'),
                $this->toggler_attributes('pdfexport')
            );
        }
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
                $this->add_cm_attribution_fields($cm, $settings['cms'][(int)$cm->id] ?? null, $attributiondefaults);
            }
        }
        // Default attribution, used by every resource without its own.
        $mform->addElement('header', 'attributionheader', get_string('attribution', 'block_pdfexport'));
        $mform->addElement('static', 'attributiondescription', '', get_string('attributiondescription', 'block_pdfexport'));
        $mform->addElement('textarea', 'authors', get_string('authors', 'block_pdfexport'), ['rows' => 3]);
        $mform->setType('authors', PARAM_TEXT);
        $mform->addHelpButton('authors', 'authors', 'block_pdfexport');
        $this->add_license_fields('');
        $mform->addHelpButton('licenseurl', 'licenseurl', 'block_pdfexport');
        // Course PDF cover stylesheet, a strict CSS subset validated in validation().
        $mform->addElement('header', 'coverheader', get_string('coverstyle', 'block_pdfexport'));
        $mform->addElement('textarea', 'covercss', get_string('covercss', 'block_pdfexport'), [
            'rows' => 10,
            'cols' => 60,
            'spellcheck' => 'false',
            'class' => 'text-monospace',
        ]);
        // Raw on purpose: cleaning would silently alter the stylesheet; cover_css::parse() validates it.
        $mform->setType('covercss', PARAM_RAW);
        $mform->addHelpButton('covercss', 'covercss', 'block_pdfexport');
        $mform->setDefault('covercss', $this->_customdata['covercss'] ?? '');
        // Element names are "cm[<id>]", so QuickForm reads their defaults from a nested array.
        $mform->setDefaults(array_merge(['cm' => $defaults], $attributiondefaults));
        $this->add_action_buttons();
    }

    /**
     * Adds the optional authorship and license override of one module, shown once it is ticked.
     *
     * @param \cm_info $cm Course module.
     * @param array|null $settings Stored override, see publication::get_settings(); null when it inherits.
     * @param array $defaults Form defaults, filled in with the override values.
     */
    private function add_cm_attribution_fields(\cm_info $cm, ?array $settings, array &$defaults): void {
        $mform = $this->_form;
        $id = (int)$cm->id;
        $name = s($cm->get_formatted_name(['escape' => false]));
        $mform->addElement(
            'advcheckbox',
            'cmoverride[' . $id . ']',
            '',
            get_string('overrideattribution', 'block_pdfexport', $name),
            null,
            [0, 1]
        );
        $mform->hideIf('cmoverride[' . $id . ']', 'cm[' . $id . ']', 'notchecked');
        $mform->addElement('textarea', 'cmauthors[' . $id . ']', get_string('cmauthors', 'block_pdfexport', $name), ['rows' => 2]);
        $mform->setType('cmauthors[' . $id . ']', PARAM_TEXT);
        $this->add_license_fields('cm', $id, $name);
        foreach (['cmauthors', 'cmlicense', 'cmcustomlicense', 'cmlicenseurl'] as $field) {
            $mform->hideIf($field . '[' . $id . ']', 'cmoverride[' . $id . ']', 'notchecked');
            $mform->hideIf($field . '[' . $id . ']', 'cm[' . $id . ']', 'notchecked');
        }
        $settings = $settings ?? ['authors' => '', 'license' => publication::INHERIT, 'customlicense' => '', 'licenseurl' => ''];
        $defaults['cmoverride'][$id] = publication::INHERIT === $settings['license'] && $settings['authors'] === '' ? 0 : 1;
        foreach ($settings as $key => $value) {
            $defaults['cm' . $key][$id] = $value;
        }
    }

    /**
     * Adds a license menu with the custom license name and URL shown only for custom licenses.
     *
     * @param string $prefix Element name and string prefix: '' for the default, 'cm' for an override.
     * @param int|null $id Course module id of an override; element names become "<name>[<id>]".
     * @param string|null $name Formatted module name of an override, used in its labels.
     */
    private function add_license_fields(string $prefix, ?int $id = null, ?string $name = null): void {
        $mform = $this->_form;
        $element = fn(string $field): string => $prefix . $field . ($id !== null ? '[' . $id . ']' : '');
        $label = fn(string $field): string => get_string($prefix . $field, 'block_pdfexport', $name);
        $options = $id !== null ? [publication::INHERIT => get_string('license_inherit', 'block_pdfexport')] : [];
        $options[''] = get_string('license_none', 'block_pdfexport');
        foreach (array_keys(publication::CC_LICENSES) as $key) {
            $options[$key] = get_string('license_' . $key, 'block_pdfexport');
        }
        $options[publication::CUSTOM] = get_string('license_custom', 'block_pdfexport');
        $mform->addElement('select', $element('license'), $label('license'), $options);
        $mform->addElement('text', $element('customlicense'), $label('customlicense'), ['size' => 50]);
        $mform->setType($element('customlicense'), PARAM_TEXT);
        $mform->addElement('text', $element('licenseurl'), $label('licenseurl'), ['size' => 50]);
        // Validated in validation(): a cleaned PARAM_URL would silently drop an invalid URL.
        $mform->setType($element('licenseurl'), PARAM_RAW_TRIMMED);
        foreach (['customlicense', 'licenseurl'] as $field) {
            $mform->hideIf($element($field), $element('license'), 'neq', publication::CUSTOM);
        }
    }

    /**
     * Requires a name for custom licenses and an HTTP(S) license URL, for the default and every
     * override, and a cover stylesheet within the supported CSS subset.
     *
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @return array Validation errors indexed by element name.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $licenses = [['customlicense', 'licenseurl', $data]];
        foreach (array_keys(array_filter((array)($data['cmoverride'] ?? []))) as $id) {
            $licenses[] = ['cmcustomlicense[' . $id . ']', 'cmlicenseurl[' . $id . ']', [
                'license' => $data['cmlicense'][$id] ?? '',
                'customlicense' => $data['cmcustomlicense'][$id] ?? '',
                'licenseurl' => $data['cmlicenseurl'][$id] ?? '',
            ]];
        }
        foreach ($licenses as [$customfield, $urlfield, $values]) {
            if (($values['license'] ?? '') !== publication::CUSTOM) {
                continue;
            }
            if (trim((string)($values['customlicense'] ?? '')) === '') {
                $errors[$customfield] = get_string('customlicenserequired', 'block_pdfexport');
            }
            $url = trim((string)($values['licenseurl'] ?? ''));
            if ($url !== '' && !publication::is_valid_url($url)) {
                $errors[$urlfield] = get_string('invalidlicenseurl', 'block_pdfexport');
            }
        }
        try {
            cover_css::parse((string)($data['covercss'] ?? ''));
        } catch (\moodle_exception $e) {
            $errors['covercss'] = $e->getMessage();
        }
        return $errors;
    }

    /**
     * Returns the submitted attribution settings, with overrides only for listed modules.
     *
     * @param \stdClass $data Submitted data.
     * @return array Settings for publication::save().
     */
    public function get_attribution_settings(\stdClass $data): array {
        $cms = [];
        foreach ($this->get_eligible_cmids() as $id) {
            if (!empty($data->cmoverride[$id])) {
                $cms[$id] = [];
                foreach (['authors', 'license', 'customlicense', 'licenseurl'] as $key) {
                    $cms[$id][$key] = $data->{'cm' . $key}[$id] ?? '';
                }
            }
        }
        return [
            'default' => [
                'authors' => $data->authors ?? '',
                'license' => $data->license ?? '',
                'customlicense' => $data->customlicense ?? '',
                'licenseurl' => $data->licenseurl ?? '',
            ],
            'cms' => $cms,
        ];
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
        $eligible = array_flip($this->get_eligible_cmids());
        $ids = [];
        foreach ((array)($data->cm ?? []) as $id => $checked) {
            if ($checked && isset($eligible[(int)$id])) {
                $ids[] = (int)$id;
            }
        }
        return $ids;
    }

    /**
     * Returns the ids of the modules listed in the form.
     *
     * @return int[]
     */
    private function get_eligible_cmids(): array {
        $ids = [];
        foreach ($this->_customdata['sections'] as $section) {
            foreach ($section['cms'] as $cm) {
                $ids[] = (int)$cm->id;
            }
        }
        return $ids;
    }
}
