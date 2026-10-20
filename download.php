<?php
// Download selected book/page as a conservative, self-contained printable PDF.
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/pdflib.php');

$courseid = required_param('courseid', PARAM_INT);
$blockid = required_param('blockid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);
$course = get_course($courseid);
$modinfo = get_fast_modinfo($course);
$cm = $modinfo->get_cm($cmid);
if ((int)$cm->course !== $courseid || !in_array($cm->modname, ['book', 'page'], true)) {
    throw new moodle_exception('invalidcoursemodule');
}
require_login($course, true, $cm);
$coursecontext = context_course::instance($courseid);
$modulecontext = context_module::instance($cmid);
require_capability('block/pdfexport:view', $coursecontext);
if (!$cm->uservisible) { throw new moodle_exception('nopermissions', 'error'); }
$block = $DB->get_record('block_instances', ['id' => $blockid, 'blockname' => 'pdfexport'], '*', MUST_EXIST);
$blockcontext = context_block::instance($blockid);
if ((int)$blockcontext->get_parent_context()->id !== (int)$coursecontext->id ||
        $block->pagetypepattern !== 'course-view-*') {
    throw new moodle_exception('invalidblock', 'block_pdfexport');
}
$config = empty($block->configdata) ? null : @unserialize(base64_decode($block->configdata), ['allowed_classes' => ['stdClass']]);
$selected = is_object($config) ? array_map('intval', explode(',', $config->selectedcmids ?? '')) : [];
if (!in_array($cmid, $selected, true)) { throw new moodle_exception('notpublished', 'block_pdfexport'); }

$transformer = new \block_pdfexport\local\print_transformer();
$parts = [];
if ($cm->modname === 'book') {
    require_capability('mod/book:read', $modulecontext);
    $book = $DB->get_record('book', ['id' => $cm->instance], '*', MUST_EXIST);
    $chapters = $DB->get_records('book_chapters', ['bookid' => $book->id], 'pagenum ASC');
    $allowhidden = has_capability('mod/book:viewhiddenchapters', $modulecontext);
    foreach ($chapters as $chapter) {
        if ($chapter->hidden && !$allowhidden) { continue; }
        $level = $chapter->subchapter ? 'h3' : 'h2';
        $text = file_rewrite_pluginfile_urls($chapter->content, 'pluginfile.php', $modulecontext->id,
            'mod_book', 'chapter', $chapter->id);
        $text = format_text($text, $chapter->contentformat, ['context' => $modulecontext, 'noclean' => false]);
        $parts[] = '<' . $level . '>' . s($chapter->title) . '</' . $level . '>' .
            $transformer->transform($text, $modulecontext, 'mod_book', 'chapter', (int)$chapter->id);
    }
} else {
    require_capability('mod/page:view', $modulecontext);
    $page = $DB->get_record('page', ['id' => $cm->instance], '*', MUST_EXIST);
    $text = file_rewrite_pluginfile_urls($page->content, 'pluginfile.php', $modulecontext->id,
        'mod_page', 'content', 0);
    $text = format_text($text, $page->contentformat, ['context' => $modulecontext, 'noclean' => false]);
    $parts[] = $transformer->transform($text, $modulecontext, 'mod_page', 'content', 0);
}
$warnings = $transformer->warnings();
$body = '<h1 style="color:#007691">' . s($cm->name) . '</h1>';
foreach ($parts as $part) { $body .= $part; }
if ($warnings) {
    $body .= '<h3 style="color:#AA004D">' . get_string('conversionwarnings', 'block_pdfexport') . '</h3><ul>';
    foreach ($warnings as $warning) { $body .= '<li>' . s($warning) . '</li>'; }
    $body .= '</ul>';
}
$pdf = new pdf('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Moodle PDF Export 0.3');
$pdf->SetTitle($cm->name);
$pdf->SetMargins(18, 20, 18);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();
$pdf->SetFont('freesans', '', 10);
try {
    $pdf->writeHTML($body, true, false, true, false, '');
    $document = $pdf->Output('', 'S');
} finally { $transformer->cleanup(); }
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="moodle-resource-' . $cmid . '.pdf"');
header('Content-Length: ' . strlen($document));
echo $document;
exit;
