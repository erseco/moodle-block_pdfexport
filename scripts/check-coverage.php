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
 * Development only: enforces the minimum PHP line coverage of the plugin from a Clover report.
 *
 * Usage: php scripts/check-coverage.php <clover.xml> [minimum percent, default 90]
 *
 * Only files under a "blocks/pdfexport/" path are counted, so the figure is the plugin's own
 * coverage even when the report also lists Moodle core files.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing, moodle.Files.MoodleInternal.MoodleInternalGlobalState

if ($argc < 2 || !is_readable($argv[1])) {
    fwrite(STDERR, "Usage: php check-coverage.php <clover.xml> [minimum]\n");
    exit(2);
}
$minimum = (float)($argv[2] ?? 90);
$xml = simplexml_load_file($argv[1]);
$statements = 0;
$covered = 0;
$files = 0;
foreach ($xml->xpath('//file') as $file) {
    if (strpos((string)$file['name'], 'blocks/pdfexport/') === false) {
        continue;
    }
    $files++;
    foreach ($file->line as $line) {
        if ((string)$line['type'] === 'stmt') {
            $statements++;
            $covered += ((int)$line['count'] > 0) ? 1 : 0;
        }
    }
}
if ($statements === 0) {
    fwrite(STDERR, "No block_pdfexport lines found in the report.\n");
    exit(1);
}
$percent = 100 * $covered / $statements;
printf(
    "block_pdfexport line coverage: %.2f%% (%d/%d lines, %d files); minimum %.2f%%\n",
    $percent,
    $covered,
    $statements,
    $files,
    $minimum
);
exit($percent + 1e-9 < $minimum ? 1 : 0);
