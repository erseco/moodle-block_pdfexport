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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/pdflib.php');

/**
 * PDF document built on Moodle's bundled TCPDF: running header, page numbers, outline and TOC.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf_document extends \pdf {
    /** @var string Unicode font bundled with Moodle (covers Latin, Greek and Cyrillic). */
    public const FONT = 'freesans';

    /** @var string Colour of structural headings. */
    private const ACCENT = '#0f4c81';

    /** @var string Text repeated in the page header. */
    private string $runningtitle;

    /** @var bool Whether the current page is the cover, which has no header or footer. */
    private bool $oncover = false;

    /**
     * Creates an A4 portrait document.
     *
     * @param string $title Document title (metadata and running header).
     * @param string $subject Document subject (metadata).
     */
    public function __construct(string $title, string $subject) {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8');
        $this->runningtitle = $title;
        $this->SetCreator(get_string('pluginname', 'block_pdfexport') . ' (Moodle)');
        $this->SetTitle($title);
        $this->SetSubject($subject);
        $this->SetMargins(18, 22, 18);
        $this->SetHeaderMargin(9);
        $this->SetFooterMargin(12);
        $this->SetAutoPageBreak(true, 20);
        $this->setPrintHeader(true);
        $this->setPrintFooter(true);
        $this->setImageScale(1.4);
        $this->SetFont(self::FONT, '', 10);
        $this->setHtmlLinksStyle([0, 70, 140], 'U');
    }

    /**
     * Draws the running header (overrides TCPDF::Header(); PHP method names are case-insensitive).
     */
    public function header() {
        if ($this->oncover) {
            return;
        }
        $this->SetFont(self::FONT, '', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 6, $this->runningtitle, 'B', 0, 'R');
        $this->SetTextColor(0, 0, 0);
    }

    /**
     * Draws the page number (overrides TCPDF::Footer()).
     */
    public function footer() {
        if ($this->oncover) {
            return;
        }
        $this->SetY(-14);
        $this->SetFont(self::FONT, '', 8);
        $this->SetTextColor(100, 100, 100);
        $text = get_string(
            'pagenumber',
            'block_pdfexport',
            ['page' => $this->getAliasNumPage(), 'total' => $this->getAliasNbPages()]
        );
        $this->Cell(0, 8, $text, 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }

    /**
     * Adds a cover page without header and footer.
     *
     * @param string $title Main title.
     * @param string $subtitle Subtitle (plain text).
     * @param string $summaryhtml Printable HTML summary.
     * @param string $footnote Plain text printed below the summary.
     */
    public function add_cover(string $title, string $subtitle, string $summaryhtml, string $footnote): void {
        $this->oncover = true;
        $this->AddPage();
        $this->SetY(70);
        $this->writeHTML('<h1 style="font-size:26pt;color:' . self::ACCENT . ';text-align:center;">' . s($title) .
            '</h1><p style="font-size:13pt;text-align:center;color:#555555;">' . s($subtitle) . '</p>');
        if ($summaryhtml !== '') {
            $this->Ln(6);
            $this->writeHTML($summaryhtml);
        }
        $this->Ln(10);
        $this->writeHTML('<p style="font-size:9pt;text-align:center;color:#777777;">' . s($footnote) . '</p>');
        // The next page starts the body; reset the flag only once the cover is finished.
        $this->endPage();
        $this->oncover = false;
    }

    /**
     * Adds a structural heading and its outline entry.
     *
     * @param string $text Heading text (plain).
     * @param int $level Outline level, 0 (section) to 3.
     * @param bool $newpage Start a new page before the heading.
     */
    public function add_heading(string $text, int $level, bool $newpage = false): void {
        if ($newpage || $this->getPage() === 0) {
            $this->AddPage();
        } else if ($this->GetY() > $this->getPageHeight() - $this->getBreakMargin() - 30) {
            // Keep the heading with the start of its content.
            $this->AddPage();
        }
        $this->Bookmark($text, $level);
        $sizes = [18, 15, 13, 11.5];
        $tag = 'h' . ($level + 1);
        $this->writeHTML('<' . $tag . ' style="font-size:' . $sizes[min($level, 3)] . 'pt;color:' . self::ACCENT . ';">' .
            s($text) . '</' . $tag . '>');
    }

    /**
     * Writes printable HTML at the current position.
     *
     * @param string $html HTML produced by print_transformer or the adapters.
     */
    public function add_html(string $html): void {
        if (trim($html) === '') {
            return;
        }
        if ($this->getPage() === 0) {
            $this->AddPage();
        }
        $this->writeHTML($html, true, false, true, false, '');
    }

    /**
     * Inserts the table of contents, built from the outline, as page 2.
     *
     * @param string $title TOC title.
     */
    public function add_toc(string $title): void {
        $this->addTOCPage();
        $this->SetFont(self::FONT, 'B', 16);
        $this->MultiCell(0, 0, $title, 0, 'L', false, 1);
        $this->Ln(4);
        $this->SetFont(self::FONT, '', 10);
        $this->addTOC(2, self::FONT, '.', $title, '', [0, 0, 0]);
        $this->endTOCPage();
    }

    /**
     * Returns the PDF bytes.
     *
     * @return string
     */
    public function get_content(): string {
        return $this->Output('', 'S');
    }
}
