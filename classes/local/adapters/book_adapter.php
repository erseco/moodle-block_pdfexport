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

namespace block_pdfexport\local\adapters;

use block_pdfexport\local\print_transformer;

/**
 * Exports a book (mod_book): every chapter and subchapter the user may read, in book order.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class book_adapter extends base_adapter {
    /**
     * Returns how much of the module content is reproduced.
     *
     * @return string
     */
    public function get_support_level(): string {
        return self::SUPPORT_FULL;
    }

    /**
     * Returns the capability needed to read the book.
     *
     * @param string $modname Module name.
     * @return string|null
     */
    protected function get_view_capability(string $modname): ?string {
        return 'mod/book:read';
    }

    /**
     * Converts the visible chapters of the book.
     *
     * @param \cm_info $cm Course module.
     * @param print_transformer $transformer Transformer.
     * @return array[] Parts.
     */
    public function export(\cm_info $cm, print_transformer $transformer): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/book/locallib.php');
        $book = $DB->get_record('book', ['id' => $cm->instance], '*', MUST_EXIST);
        // Core ordering and numbering; subchapters of a hidden chapter are reported hidden too.
        $chapters = book_preload_chapters($book);
        $viewhidden = has_capability('mod/book:viewhiddenchapters', $cm->context);
        $parts = [];
        foreach ($chapters as $chapter) {
            if ($chapter->hidden && !$viewhidden) {
                continue;
            }
            $parts[] = [
                'title' => html_entity_decode(
                    book_get_chapter_title($chapter->id, $chapters, $book, $cm->context),
                    ENT_QUOTES | ENT_HTML5
                ),
                'level' => $chapter->subchapter ? 2 : 1,
                'html' => $this->format_html(
                    $chapter->content,
                    (int)$chapter->contentformat,
                    $cm->context,
                    'mod_book',
                    'chapter',
                    (int)$chapter->id,
                    $transformer
                ),
            ];
        }
        if (!$parts) {
            $parts[] = ['title' => null, 'level' => 0,
                'html' => $this->notice(get_string('emptybook', 'block_pdfexport'))];
        }
        return $parts;
    }
}
