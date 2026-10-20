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
 * Restore task for block_pdfexport.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Remaps the published course module ids after a course restore.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_pdfexport_block_task extends restore_block_task {
    /**
     * No particular settings.
     */
    protected function define_my_settings() {
    }

    /**
     * No particular steps.
     */
    protected function define_my_steps() {
    }

    /**
     * No file areas.
     *
     * @return array
     */
    public function get_fileareas() {
        return [];
    }

    /**
     * No encoded configuration attributes.
     *
     * @return array
     */
    public function get_configdata_encoded_attributes() {
        return [];
    }

    /**
     * Remaps the selection once all activities are restored; unrestored modules are dropped.
     */
    public function after_restore() {
        global $DB;
        $blockid = $this->get_blockid();
        $configdata = $DB->get_field('block_instances', 'configdata', ['id' => $blockid]);
        if (!$configdata) {
            return;
        }
        $config = $this->decode_configdata($configdata);
        $ids = [];
        foreach (\block_pdfexport\local\publication::get_selected_cmids($config) as $oldid) {
            $mapping = restore_dbops::get_backup_ids_record($this->get_restoreid(), 'course_module', $oldid);
            if ($mapping && $mapping->newitemid) {
                $ids[] = (int)$mapping->newitemid;
            }
        }
        $config->selectedcmids = implode(',', $ids);
        $DB->set_field('block_instances', 'configdata', base64_encode(serialize($config)), ['id' => $blockid]);
    }

    /**
     * No content to decode.
     *
     * @return array
     */
    public static function define_decode_contents() {
        return [];
    }

    /**
     * No decoding rules.
     *
     * @return array
     */
    public static function define_decode_rules() {
        return [];
    }
}
