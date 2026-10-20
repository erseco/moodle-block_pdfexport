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
 * Spanish strings for block_pdfexport.
 *
 * @package    block_pdfexport
 * @copyright  2026 HenarLG and block_pdfexport contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['contents'] = 'Índice';
$string['conversionwarnings'] = 'Advertencias de conversión';
$string['coursepdfpreview'] = 'Descargar el PDF del curso con la selección guardada';
$string['downloadcoursepdf'] = 'Descargar PDF del curso';
$string['downloadpdf'] = 'Descargar PDF';
$string['downloadpdfof'] = 'Descargar PDF de {$a}';
$string['emptybook'] = 'Este libro no tiene capítulos que exportar.';
$string['emptyfolder'] = 'Esta carpeta no contiene archivos.';
$string['exelearninginteractive'] = '[Actividad interactiva omitida: {$a}. Ábrela en Moodle para realizarla.]';
$string['exelearningnotice'] = 'Las partes interactivas de esta actividad de eXeLearning no se incluyen. Ábrela en Moodle:';
$string['exelearningpage'] = 'Página';
$string['exportfailed'] = 'No se ha podido convertir este recurso. Ábrelo en Moodle.';
$string['filenotembedded'] = 'Este tipo de archivo no se puede incrustar en el PDF. Descárgalo desde Moodle:';
$string['foldernotembedded'] = 'Los archivos de esta carpeta se enumeran, pero no se incrustan en el PDF.';
$string['foldertruncated'] = '... y {$a} archivos más.';
$string['generatedon'] = 'Generado el {$a}';
$string['imagenodescription'] = 'sin descripción';
$string['imagenotincluded'] = '[Imagen no incluida: {$a}]';
$string['interactiveplaceholder'] = '[Contenido interactivo omitido]';
$string['invalidblock'] = 'Este bloque no pertenece al curso indicado.';
$string['manageresources'] = 'Gestionar recursos descargables';
$string['mediaplaceholder'] = '[Multimedia incrustado: {$a}]';
$string['mediaunavailable'] = 'consulta el recurso original en Moodle';
$string['nocandidates'] = 'No se han encontrado recursos exportables en este curso.';
$string['noresources'] = 'No hay recursos publicados en este bloque.';
$string['nothingtoexport'] = 'No hay contenido publicado disponible para exportar.';
$string['notpublished'] = 'Este recurso no está publicado para su descarga o no está disponible para ti.';
$string['openinmoodle'] = 'Ábrela en Moodle:';
$string['openoriginal'] = 'Abrir el recurso original';
$string['pagenumber'] = 'Página {$a->page} de {$a->total}';
$string['pdfexport:addinstance'] = 'Añadir el bloque de descarga en PDF';
$string['pdfexport:configure'] = 'Seleccionar los recursos publicados del curso';
$string['pdfexport:myaddinstance'] = 'Añadir PDF Export al área personal';
$string['pdfexport:view'] = 'Ver y descargar los recursos publicados';
$string['pluginname'] = 'Descarga de contenidos en PDF';
$string['privacy:metadata'] = 'El bloque de descarga en PDF solo guarda en su configuración la lista de módulos publicados. No almacena datos personales.';
$string['referenceonly'] = 'Esta actividad es interactiva y no se puede reproducir en un PDF.';
$string['selectiondescription'] = 'Selecciona los recursos que se publican en este bloque. El alumnado puede descargar cada recurso compatible y la selección completa como un único PDF del curso. Las actividades interactivas solo se incluyen como descripción con un enlace.';
$string['support_full'] = 'Contenido completo';
$string['support_partial'] = 'Contenido parcial';
$string['support_reference'] = 'Solo enlace';
$string['toomanyresources'] = 'La selección contiene {$a->count} recursos; el máximo para un único PDF es {$a->max}. Publica menos recursos.';
$string['warning_hiddencontent'] = 'No se ha incluido el contenido que estaba oculto en la página original.';
$string['warning_imageformat'] = 'No se ha incluido una imagen en un formato no compatible (solo se incrustan PNG y JPEG).';
$string['warning_imagelimit'] = 'Se ha alcanzado el límite de imágenes de un PDF; no se han incluido más imágenes.';
$string['warning_imagenotfound'] = 'No se ha encontrado una imagen o no está guardada en este recurso, por lo que no se ha incluido.';
$string['warning_imagetoolarge'] = 'Una imagen supera el límite de tamaño y no se ha incluido.';
$string['warning_interactive'] = 'Los elementos interactivos (formularios, scripts o gráficos) no se pueden reproducir y se han omitido.';
$string['warning_media'] = 'El audio, vídeo o contenido externo incrustado se ha sustituido por un enlace.';
$string['warning_remoteimage'] = 'Las imágenes externas nunca se descargan durante la exportación y no se han incluido.';
