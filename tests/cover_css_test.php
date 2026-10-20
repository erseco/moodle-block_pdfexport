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

namespace block_pdfexport;

use block_pdfexport\local\cover_css;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the cover CSS parser: supported subset, canonical output and rejected input.
 *
 * @package    block_pdfexport
 * @category   test
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(cover_css::class)]
final class cover_css_test extends \advanced_testcase {
    /**
     * Supported stylesheets give canonical styles, whatever the formatting and order.
     */
    public function test_parse_valid(): void {
        $this->assertSame([], cover_css::parse(''));
        $this->assertSame([], cover_css::parse(" \n/* Only a comment: «título» */\n"));
        $this->assertSame([], cover_css::parse('.pdfexport-cover-title {}'));

        $css = "/* Cover style */\r\n.pdfexport-cover-date { font-size: 9pt; COLOR: #777 }\n" .
            ".pdfexport-cover-title{text-align:center;font-size:30PT;color:#123456;}\n" .
            ".pdfexport-cover-subtitle, .pdfexport-cover-summary {\n\tcolor: #ABCDEF;\n\tfont-style: Italic;\n}\n" .
            ".pdfexport-cover { background-color: #fdf6e3; font-weight: bold; line-height: 1.50 }\n" .
            ".pdfexport-cover-attribution { text-align: justify; font-weight: normal; font-size: 6pt }\n" .
            ".pdfexport-cover-title { color: #ff0000; font-size: 10.5pt; }\n";
        $this->assertSame([
            'cover' => ['background-color' => '#fdf6e3', 'font-weight' => 'bold', 'line-height' => '1.5'],
            'title' => ['color' => '#ff0000', 'font-size' => '10.5pt', 'text-align' => 'center'],
            'subtitle' => ['color' => '#abcdef', 'font-style' => 'italic'],
            'attribution' => ['font-size' => '6pt', 'font-weight' => 'normal', 'text-align' => 'justify'],
            'summary' => ['color' => '#abcdef', 'font-style' => 'italic'],
            'date' => ['color' => '#777777', 'font-size' => '9pt'],
        ], cover_css::parse($css));

        // Limits are inclusive.
        $styles = cover_css::parse('.pdfexport-cover-title { font-size: 72pt; line-height: 3 }' .
            '.pdfexport-cover-date { font-size: 6pt; line-height: 0.8 }');
        $this->assertSame(['font-size' => '72pt', 'line-height' => '3'], $styles['title']);
        $this->assertSame(['font-size' => '6pt', 'line-height' => '0.8'], $styles['date']);
        $this->assertSame([], cover_css::parse(str_repeat(' ', cover_css::MAX_LENGTH)));
    }

    /**
     * Element declarations inherit the cover rule, override it and never get its background.
     */
    public function test_declarations(): void {
        $styles = cover_css::parse('.pdfexport-cover { color: #111111; background-color: #222222; font-weight: bold }' .
            '.pdfexport-cover-title { color: #333333; font-size: 30pt }');
        $this->assertSame('color:#333333;font-weight:bold;font-size:30pt;', cover_css::declarations($styles, 'title'));
        $this->assertSame('color:#111111;font-weight:bold;', cover_css::declarations($styles, 'date'));
        $this->assertSame('', cover_css::declarations([], 'title'));
        $this->assertSame('', cover_css::declarations(['cover' => ['background-color' => '#222222']], 'summary'));
    }

    /**
     * Unsupported, malformed and dangerous input is rejected with the right message.
     *
     * @param string $css Stylesheet.
     * @param string $error Expected language string.
     */
    #[DataProvider('invalid_provider')]
    public function test_parse_invalid(string $css, string $error): void {
        try {
            cover_css::parse($css);
            $this->fail('Accepted: ' . $css);
        } catch (\moodle_exception $e) {
            $this->assertSame($error, $e->errorcode);
            // Messages are localised and never echo markup.
            $this->assertStringNotContainsString('<', $e->getMessage());
        }
    }

    /**
     * Rejected stylesheets.
     *
     * @return array
     */
    public static function invalid_provider(): array {
        $title = '.pdfexport-cover-title';
        return [
            'remote import' => ['@import "https://evil.example/x.css";', 'covercsscharacters'],
            'bare import' => ['@import url(https://evil.example/x.css);', 'covercsscharacters'],
            'font face' => ['@font-face { font-family: x; src: url(/etc/passwd) }', 'covercsscharacters'],
            'media query' => ['@media print { ' . $title . ' { color: #000 } }', 'covercsscharacters'],
            'remote background' => [$title . ' { background-image: url(https://evil.example/a.png) }', 'covercsscharacters'],
            'local file' => [$title . ' { background: url(file:///etc/passwd) }', 'covercsscharacters'],
            'data uri' => [$title . ' { background: url(data:image/png;base64,AAAA) }', 'covercsscharacters'],
            'unquoted url keyword' => [$title . ' { background-image: url }', 'covercssproperty'],
            'expression' => [$title . ' { color: expression(alert(1)) }', 'covercsscharacters'],
            'javascript' => [$title . ' { color: javascript:alert(1) }', 'covercsscharacters'],
            'escape' => [$title . ' { c\6f lor: #000 }', 'covercsscharacters'],
            'escaped url' => [$title . ' { background: \75 rl(x) }', 'covercsscharacters'],
            'html injection' => [$title . ' { color: #000 }</style><script>alert(1)</script>', 'covercsscharacters'],
            'attribute breakout' => [$title . ' { color: #000" onload="x }', 'covercsscharacters'],
            'important' => [$title . ' { color: #000 !important }', 'covercsscharacters'],
            'rgb function' => [$title . ' { color: rgb(0, 0, 0) }', 'covercsscharacters'],
            'calc' => [$title . ' { font-size: calc(10pt + 2pt) }', 'covercsscharacters'],
            'variable declaration' => [$title . ' { --accent: #000 }', 'covercssproperty'],
            'variable use' => [$title . ' { color: var(--accent) }', 'covercsscharacters'],
            'universal selector' => ['* { color: #000 }', 'covercsscharacters'],
            'body selector' => ['body { color: #000 }', 'covercssselector'],
            'html selector' => ['html, ' . $title . ' { color: #000 }', 'covercssselector'],
            'element selector' => ['h1 { color: #000 }', 'covercssselector'],
            'descendant selector' => ['.pdfexport-cover ' . $title . ' { color: #000 }', 'covercssselector'],
            'child selector' => ['.pdfexport-cover > h1 { color: #000 }', 'covercsscharacters'],
            'unknown class' => ['.pdfexport-toc { color: #000 }', 'covercssselector'],
            'id selector' => ['#cover { color: #000 }', 'covercssselector'],
            'pseudo class' => [$title . ':hover { color: #000 }', 'covercssselector'],
            'empty selector' => ['{ color: #000 }', 'covercssselector'],
            'position' => [$title . ' { position: absolute }', 'covercssproperty'],
            'display flex' => [$title . ' { display: flex }', 'covercssproperty'],
            'grid' => [$title . ' { grid-template-columns: 1fr }', 'covercssproperty'],
            'animation' => [$title . ' { animation: spin 1s }', 'covercssproperty'],
            'margin' => [$title . ' { margin: 10mm }', 'covercssproperty'],
            'font family' => [$title . ' { font-family: courier }', 'covercssproperty'],
            'named colour' => [$title . ' { color: red }', 'covercssvalue'],
            'short hex' => [$title . ' { color: #12 }', 'covercssvalue'],
            'bad hex' => [$title . ' { color: #12345g }', 'covercssvalue'],
            'pixels' => [$title . ' { font-size: 12px }', 'covercssvalue'],
            'unitless size' => [$title . ' { font-size: 12 }', 'covercssvalue'],
            'tiny size' => [$title . ' { font-size: 5.99pt }', 'covercssvalue'],
            'huge size' => [$title . ' { font-size: 73pt }', 'covercssvalue'],
            'enormous size' => [$title . ' { font-size: 99999999999pt }', 'covercssvalue'],
            'numeric weight' => [$title . ' { font-weight: 700 }', 'covercssvalue'],
            'two values' => [$title . ' { font-style: bold italic }', 'covercssvalue'],
            'empty value' => [$title . ' { color: }', 'covercssvalue'],
            'huge line height' => [$title . ' { line-height: 4 }', 'covercssvalue'],
            'line height unit' => [$title . ' { line-height: 12pt }', 'covercssvalue'],
            'missing colon' => [$title . ' { color #000 }', 'covercsssyntax'],
            'missing property' => [$title . ' { : #000 }', 'covercsssyntax'],
            'unbalanced open' => [$title . ' { color: #000', 'covercsssyntax'],
            'unbalanced close' => [$title . ' color: #000 }', 'covercsssyntax'],
            'nested' => ['.pdfexport-cover { ' . $title . ' { color: #000 } }', 'covercsssyntax'],
            'bare declaration' => ['color: #000;', 'covercsssyntax'],
            'trailing text' => [$title . ' { color: #000 } trailing', 'covercsssyntax'],
            'unterminated comment' => [$title . ' { color: #000 } /* open', 'covercsscharacters'],
            'invalid utf-8' => [$title . " { color: #000 } /* \xC3\x28 */", 'covercsscharacters'],
            'control character' => [$title . " { color: #000\x00 }", 'covercsscharacters'],
            'too long' => [str_repeat(' ', cover_css::MAX_LENGTH + 1), 'covercsstoolong'],
        ];
    }
}
