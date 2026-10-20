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
 * Coverage information for block_pdfexport.
 *
 * @package    block_pdfexport
 * @category   test
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Scopes coverage to the plugin logic: every class, the block class and the restore task.
 *
 * Only the two entry-point scripts (manage.php, download.php) stay outside the PHPUnit figure:
 * they hold no logic beyond parameter parsing, require_login() and calls into the classes, and
 * Behat exercises them end to end. Nothing in classes/ is excluded (ADR-0005).
 */
return new class extends phpunit_coverage_info {
    /** @var array Folders relative to the plugin root to include in coverage. */
    protected $includelistfolders = [
        'classes',
        'backup',
    ];

    /** @var array Files relative to the plugin root to include in coverage. */
    protected $includelistfiles = [
        'block_pdfexport.php',
    ];

    /** @var array Folders to exclude: test infrastructure only. */
    protected $excludelistfolders = [
        'tests/generator',
    ];

    /** @var array Files to exclude. */
    protected $excludelistfiles = [];
};
