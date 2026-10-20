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
 * English strings for block_pdfexport.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['attribution'] = 'Default authorship and license';
$string['attributiondescription'] = 'These details are printed in the PDFs of every published resource that has no specific authorship and license. Leave them empty if they are not known: no license is ever assumed.';
$string['authors'] = 'Authors or organizations';
$string['authors_help'] = 'Enter one author or organization per line. Leave empty if attribution is not known.';
$string['authorslabel'] = 'Authors';
$string['authorsnone'] = 'Not specified';
$string['cmauthors'] = '{$a}: authors or organizations (empty: default authors)';
$string['cmcustomlicense'] = '{$a}: custom license name';
$string['cmlicense'] = '{$a}: license';
$string['cmlicenseurl'] = '{$a}: license URL (optional)';
$string['contents'] = 'Contents';
$string['conversionwarnings'] = 'Conversion warnings';
$string['coursepdfpreview'] = 'Download the course PDF with the saved selection';
$string['customlicense'] = 'Custom license name';
$string['customlicenserequired'] = 'Enter a name for the custom license.';
$string['downloadcoursepdf'] = 'Download course PDF';
$string['downloadpdf'] = 'Download PDF';
$string['downloadpdfof'] = 'Download PDF of {$a}';
$string['emptybook'] = 'This book has no chapters to export.';
$string['emptyfolder'] = 'This folder contains no files.';
$string['exelearninginteractive'] = '[Interactive activity omitted: {$a}. Open it in Moodle to complete it.]';
$string['exelearningnotice'] = 'Interactive parts of this eXeLearning activity are not included. Open it in Moodle:';
$string['exelearningpage'] = 'Page';
$string['exportfailed'] = 'This resource could not be converted. Open it in Moodle.';
$string['filenotembedded'] = 'This file type cannot be embedded in the PDF. Download it from Moodle:';
$string['foldernotembedded'] = 'The files in this folder are listed but not embedded in the PDF.';
$string['foldertruncated'] = '... and {$a} more files.';
$string['generatedon'] = 'Generated on {$a}';
$string['imagenodescription'] = 'no description';
$string['imagenotincluded'] = '[Image not included: {$a}]';
$string['interactiveplaceholder'] = '[Interactive content omitted]';
$string['invalidblock'] = 'This block does not belong to the specified course.';
$string['invalidlicenseurl'] = 'Enter a valid HTTP or HTTPS URL.';
$string['license'] = 'Content license';
$string['license_cc_by'] = 'CC BY 4.0';
$string['license_cc_by_nc'] = 'CC BY-NC 4.0';
$string['license_cc_by_nc_nd'] = 'CC BY-NC-ND 4.0';
$string['license_cc_by_nc_sa'] = 'CC BY-NC-SA 4.0';
$string['license_cc_by_nd'] = 'CC BY-ND 4.0';
$string['license_cc_by_sa'] = 'CC BY-SA 4.0';
$string['license_custom'] = 'Custom license';
$string['license_inherit'] = 'Default license';
$string['license_none'] = 'No license specified';
$string['licenselabel'] = 'License';
$string['licenseurl'] = 'License URL (optional)';
$string['licenseurl_help'] = 'Link to the custom license terms. Creative Commons licenses always link to their official page.';
$string['manageresources'] = 'Manage downloadable resources';
$string['mediaplaceholder'] = '[Embedded media: {$a}]';
$string['mediaunavailable'] = 'view the original resource in Moodle';
$string['nocandidates'] = 'No exportable resources were found in this course.';
$string['noresources'] = 'No resources have been published for this block.';
$string['nothingtoexport'] = 'There is no published content available to you for export.';
$string['notpublished'] = 'This resource is not published for download or is not available to you.';
$string['openinmoodle'] = 'Open it in Moodle:';
$string['openoriginal'] = 'Open original resource';
$string['overrideattribution'] = 'Specific authorship and license for {$a}';
$string['pagenumber'] = 'Page {$a->page} of {$a->total}';
$string['pdfexport:addinstance'] = 'Add a PDF Export block';
$string['pdfexport:configure'] = 'Choose which course resources are published';
$string['pdfexport:myaddinstance'] = 'Add PDF Export to Dashboard';
$string['pdfexport:view'] = 'View and download the published resources';
$string['pluginname'] = 'PDF Export';
$string['privacy:metadata'] = 'The PDF Export block stores no personal data linked to users. Its configuration holds the published course modules and the optional authorship and license of the content, typed by teachers as free text. PDFs are generated on request and are not kept.';
$string['referenceonly'] = 'This activity is interactive and cannot be reproduced in a PDF.';
$string['selectiondescription'] = 'Select the resources to publish in this block. Students can download each supported resource, and the whole selection as a single course PDF. Interactive activities are included only as a description with a link.';
$string['support_full'] = 'Full content';
$string['support_partial'] = 'Partial content';
$string['support_reference'] = 'Link only';
$string['toomanyresources'] = 'The selection contains {$a->count} resources; the maximum for a single PDF is {$a->max}. Publish fewer resources.';
$string['warning_hiddencontent'] = 'Content hidden in the original page was not included.';
$string['warning_imageformat'] = 'An image in an unsupported format was not included (only PNG and JPEG are embedded).';
$string['warning_imagelimit'] = 'The image limit for a single PDF was reached; further images were not included.';
$string['warning_imagenotfound'] = 'An image could not be found or is not stored in this resource, so it was not included.';
$string['warning_imagetoolarge'] = 'An image exceeds the size limit and was not included.';
$string['warning_interactive'] = 'Interactive elements (forms, scripts or graphics) cannot be reproduced and were omitted.';
$string['warning_media'] = 'Embedded audio, video or external content was replaced by a link.';
$string['warning_remoteimage'] = 'External images are never downloaded during export and were not included.';
