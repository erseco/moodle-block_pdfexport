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

namespace block_pdfexport\local;

/**
 * Reads and writes the published selection stored in the block instance configuration.
 *
 * The selection is the comma-separated "selectedcmids" property of the block config, the
 * format used since the first alpha release, so existing instances keep their selection.
 * Attribution is optional: the default in "authors", "license", "customlicense" and
 * "licenseurl", and resource overrides in "cmattribution" (keyed by course module id).
 * No license is ever assumed: an empty configuration prints no attribution.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class publication {
    /** @var string[] Creative Commons 4.0 licenses offered, with their official deeds. */
    public const CC_LICENSES = [
        'cc_by' => 'https://creativecommons.org/licenses/by/4.0/',
        'cc_by_sa' => 'https://creativecommons.org/licenses/by-sa/4.0/',
        'cc_by_nc' => 'https://creativecommons.org/licenses/by-nc/4.0/',
        'cc_by_nc_sa' => 'https://creativecommons.org/licenses/by-nc-sa/4.0/',
        'cc_by_nd' => 'https://creativecommons.org/licenses/by-nd/4.0/',
        'cc_by_nc_nd' => 'https://creativecommons.org/licenses/by-nc-nd/4.0/',
    ];

    /** @var string License value of a custom license, named by the teacher. */
    public const CUSTOM = 'custom';

    /** @var string License value of a resource that uses the block default license. */
    public const INHERIT = 'inherit';

    /**
     * Returns the published course module ids of a block configuration.
     *
     * @param mixed $config Block config (block_base::$config), possibly null or tampered with.
     * @return int[] Positive unique ids.
     */
    public static function get_selected_cmids($config): array {
        if (!is_object($config) || !isset($config->selectedcmids) || !is_scalar($config->selectedcmids)) {
            return [];
        }
        $ids = array_map('intval', explode(',', (string)$config->selectedcmids));
        return array_values(array_unique(array_filter($ids, fn(int $id): bool => $id > 0)));
    }

    /**
     * Returns the stored attribution settings, normalised: the block default and the
     * resource overrides keyed by course module id.
     *
     * Legacy configurations without attribution give an empty default and no overrides.
     *
     * @param mixed $config Block config, possibly null or tampered with.
     * @return array ['default' => settings, 'cms' => [cmid => settings]], see normalise_settings().
     */
    public static function get_settings($config): array {
        $config = is_object($config) ? $config : new \stdClass();
        $cms = [];
        if (isset($config->cmattribution) && is_array($config->cmattribution)) {
            foreach ($config->cmattribution as $cmid => $raw) {
                $settings = self::normalise_settings($raw, true);
                if ((int)$cmid > 0 && !self::is_inherited($settings)) {
                    $cms[(int)$cmid] = $settings;
                }
            }
        }
        return ['default' => self::normalise_settings($config, false), 'cms' => $cms];
    }

    /**
     * Returns the default (block level) attribution of a block configuration.
     *
     * @param mixed $config Block config, possibly null or tampered with.
     * @return array ['authors' => string[], 'license' => string, 'licenseurl' => string], plain text.
     */
    public static function get_attribution($config): array {
        return self::resolve(self::get_settings($config)['default'], null);
    }

    /**
     * Returns the attribution of the resources that override the block default.
     *
     * Empty override fields inherit the default; a license explicitly set to "none" does not.
     *
     * @param mixed $config Block config, possibly null or tampered with.
     * @return array[] Attribution (see get_attribution()) keyed by course module id.
     */
    public static function get_cm_attributions($config): array {
        $settings = self::get_settings($config);
        $default = self::resolve($settings['default'], null);
        return array_map(fn(array $cm): array => self::resolve($cm, $default), $settings['cms']);
    }

    /**
     * Checks that a URL is an absolute HTTP(S) URL, the only schemes allowed as license links.
     *
     * @param string $url Candidate URL.
     * @return bool
     */
    public static function is_valid_url(string $url): bool {
        $parts = parse_url($url);
        return is_array($parts) && !empty($parts['host']) && isset($parts['scheme']) &&
            in_array(strtolower($parts['scheme']), ['http', 'https'], true) &&
            filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Stores a new selection and, optionally, new attribution settings, keeping any other
     * configuration properties.
     *
     * @param \block_base $block Block instance.
     * @param int[] $cmids Course module ids, already validated against the course.
     * @param array|null $settings Attribution settings shaped as get_settings(); null keeps the stored ones.
     */
    public static function save(\block_base $block, array $cmids, ?array $settings = null): void {
        $config = is_object($block->config) ? clone $block->config : new \stdClass();
        $config->selectedcmids = implode(',', self::get_selected_cmids((object)['selectedcmids' => implode(',', $cmids)]));
        if ($settings !== null) {
            foreach (self::normalise_settings($settings['default'] ?? [], false) as $key => $value) {
                $config->$key = $value;
            }
            $config->cmattribution = self::get_settings((object)['cmattribution' => $settings['cms'] ?? []])['cms'];
        }
        $block->instance_config_save($config);
        $block->config = $config;
    }

    /**
     * Normalises stored or submitted attribution settings.
     *
     * Unknown licenses become "none" and the URL is kept only for custom licenses, so a
     * stale URL never mislabels a Creative Commons license.
     *
     * @param mixed $raw Object or array with authors, license, customlicense and licenseurl.
     * @param bool $resource Whether these are resource settings, which may inherit the license.
     * @return array Settings with the same keys; authors one per line.
     */
    private static function normalise_settings($raw, bool $resource): array {
        $raw = is_object($raw) || is_array($raw) ? (array)$raw : [];
        $text = fn(string $key): string => isset($raw[$key]) && is_scalar($raw[$key]) ? (string)$raw[$key] : '';
        $authors = array_map([self::class, 'clean'], preg_split('/\R/u', $text('authors')));
        $authors = array_filter($authors, fn(string $author): bool => $author !== '');
        $license = self::clean($text('license'));
        $licenses = array_merge(array_keys(self::CC_LICENSES), [self::CUSTOM], $resource ? [self::INHERIT] : []);
        if (!in_array($license, $licenses, true)) {
            // A resource without a stored license inherits; anything else unknown means "none".
            $license = $resource && !isset($raw['license']) ? self::INHERIT : '';
        }
        $custom = $license === self::CUSTOM;
        $url = $custom ? self::clean($text('licenseurl')) : '';
        return [
            'authors' => implode("\n", array_unique($authors)),
            'license' => $license,
            'customlicense' => $custom ? self::clean($text('customlicense')) : '',
            'licenseurl' => self::is_valid_url($url) ? $url : '',
        ];
    }

    /**
     * Resolves normalised settings into the attribution printed in a PDF.
     *
     * @param array $settings Settings from normalise_settings().
     * @param array|null $default Resolved default the settings inherit from, null for the default itself.
     * @return array See get_attribution().
     */
    private static function resolve(array $settings, ?array $default): array {
        $authors = $settings['authors'] !== '' ? explode("\n", $settings['authors']) : ($default['authors'] ?? []);
        $license = $settings['license'];
        if ($license === self::INHERIT) {
            return ['authors' => $authors, 'license' => $default['license'], 'licenseurl' => $default['licenseurl']];
        }
        if (isset(self::CC_LICENSES[$license])) {
            $name = get_string('license_' . $license, 'block_pdfexport');
            return ['authors' => $authors, 'license' => $name, 'licenseurl' => self::CC_LICENSES[$license]];
        }
        // A custom license without a name is not a license: never print a bare URL as one.
        $name = $license === self::CUSTOM ? $settings['customlicense'] : '';
        return ['authors' => $authors, 'license' => $name, 'licenseurl' => $name !== '' ? $settings['licenseurl'] : ''];
    }

    /**
     * Whether resource settings add nothing to the block default.
     *
     * @param array $settings Settings from normalise_settings().
     * @return bool
     */
    private static function is_inherited(array $settings): bool {
        return $settings['authors'] === '' && $settings['license'] === self::INHERIT;
    }

    /**
     * Returns one line of plain text: valid UTF-8, no control characters, trimmed.
     *
     * @param string $text Text.
     * @return string
     */
    private static function clean(string $text): string {
        return trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', fix_utf8($text)) ?? '');
    }

    /**
     * Loads and validates a PDF Export block instance of a course.
     *
     * Request parameters are never trusted: the block must exist, be a PDF Export block and
     * live in the context of the given course (not in another course, the front page or a
     * user dashboard).
     *
     * @param int $blockid Block instance id.
     * @param int $courseid Course id.
     * @return \block_base Block instance with its configuration loaded.
     * @throws \moodle_exception When the block does not belong to the course.
     */
    public static function get_course_block(int $blockid, int $courseid): \block_base {
        global $DB;
        $record = $DB->get_record('block_instances', ['id' => $blockid, 'blockname' => 'pdfexport']);
        $coursecontext = \context_course::instance($courseid, IGNORE_MISSING);
        if (
            !$record || !$coursecontext || $courseid == SITEID ||
                (int)$record->parentcontextid !== (int)$coursecontext->id
        ) {
            throw new \moodle_exception('invalidblock', 'block_pdfexport');
        }
        return block_instance('pdfexport', $record);
    }
}
