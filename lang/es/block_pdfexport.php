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

$string['attribution'] = 'Autoría y licencia por defecto';
$string['attributiondescription'] = 'Estos datos se imprimen en los PDF de todos los recursos publicados que no tengan una autoría y licencia específicas. Déjalos vacíos si no se conocen: nunca se asume ninguna licencia.';
$string['authors'] = 'Autores u organizaciones';
$string['authors_help'] = 'Escribe un autor u organización en cada línea. Déjalo vacío si no se conoce la autoría.';
$string['authorslabel'] = 'Autores';
$string['authorsnone'] = 'Sin especificar';
$string['cmauthors'] = '{$a}: autores u organizaciones (vacío: autores por defecto)';
$string['cmcustomlicense'] = '{$a}: nombre de la licencia personalizada';
$string['cmlicense'] = '{$a}: licencia';
$string['cmlicenseurl'] = '{$a}: URL de la licencia (opcional)';
$string['contents'] = 'Índice';
$string['conversionwarnings'] = 'Advertencias de conversión';
$string['coursepdfpreview'] = 'Descargar el PDF del curso con la selección guardada';
$string['covercss'] = 'CSS de la portada';
$string['covercss_help'] = 'Estilos opcionales solo para la portada del PDF del curso. Nunca afectan al índice, al contenido ni a los PDF de recursos individuales. Vacía el campo para recuperar la portada predeterminada.

Selectores: .pdfexport-cover (todos los elementos de la portada; su color de fondo rellena la página de portada), .pdfexport-cover-title, .pdfexport-cover-subtitle, .pdfexport-cover-attribution, .pdfexport-cover-summary, .pdfexport-cover-date.

Propiedades: color y background-color (#rgb o #rrggbb), font-size (de 6pt a 72pt), font-weight (normal, bold), font-style (normal, italic), text-align (left, center, right, justify), line-height (de 0.8 a 3).

Ejemplo: .pdfexport-cover-title { color: #123456; font-size: 30pt; }

El motor de PDF solo admite este subconjunto: se rechazan URL, importaciones, fuentes, imágenes, funciones y cualquier otro CSS.';
$string['covercsscharacters'] = 'El CSS de la portada contiene caracteres no permitidos. Usa solo letras, dígitos, espacios y los símbolos # . , : ; { } - (se admiten comentarios). No se admiten URL, comillas, secuencias de escape, funciones como url() ni reglas @ como @import.';
$string['covercssproperty'] = 'La propiedad "{$a}" no está admitida en el CSS de la portada.';
$string['covercssselector'] = 'El selector "{$a}" no está admitido en el CSS de la portada. Usa solo los selectores .pdfexport-cover documentados.';
$string['covercsssyntax'] = 'El CSS de la portada está mal formado: escribe cada regla como selectores seguidos de declaraciones "propiedad: valor;" entre { y }, sin anidar.';
$string['covercsstoolong'] = 'El CSS de la portada no puede superar los {$a} bytes.';
$string['covercssvalue'] = 'El valor "{$a->value}" no está admitido para la propiedad {$a->property} en el CSS de la portada.';
$string['coverstyle'] = 'Estilo de la portada del PDF del curso';
$string['customlicense'] = 'Nombre de la licencia personalizada';
$string['customlicenserequired'] = 'Escribe un nombre para la licencia personalizada.';
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
$string['invalidlicenseurl'] = 'Escribe una URL HTTP o HTTPS válida.';
$string['license'] = 'Licencia del contenido';
$string['license_cc_by'] = 'CC BY 4.0';
$string['license_cc_by_nc'] = 'CC BY-NC 4.0';
$string['license_cc_by_nc_nd'] = 'CC BY-NC-ND 4.0';
$string['license_cc_by_nc_sa'] = 'CC BY-NC-SA 4.0';
$string['license_cc_by_nd'] = 'CC BY-ND 4.0';
$string['license_cc_by_sa'] = 'CC BY-SA 4.0';
$string['license_custom'] = 'Licencia personalizada';
$string['license_inherit'] = 'Licencia por defecto';
$string['license_none'] = 'Sin licencia especificada';
$string['licenselabel'] = 'Licencia';
$string['licenseurl'] = 'URL de la licencia (opcional)';
$string['licenseurl_help'] = 'Enlace a las condiciones de la licencia personalizada. Las licencias Creative Commons siempre enlazan a su página oficial.';
$string['manageresources'] = 'Gestionar recursos descargables';
$string['mediaplaceholder'] = '[Multimedia incrustado: {$a}]';
$string['mediaunavailable'] = 'consulta el recurso original en Moodle';
$string['nocandidates'] = 'No se han encontrado recursos exportables en este curso.';
$string['noresources'] = 'No hay recursos publicados en este bloque.';
$string['nothingtoexport'] = 'No hay contenido publicado disponible para exportar.';
$string['notpublished'] = 'Este recurso no está publicado para su descarga o no está disponible para ti.';
$string['openinmoodle'] = 'Ábrela en Moodle:';
$string['openoriginal'] = 'Abrir el recurso original';
$string['overrideattribution'] = 'Autoría y licencia específicas para {$a}';
$string['pagenumber'] = 'Página {$a->page} de {$a->total}';
$string['pdfexport:addinstance'] = 'Añadir el bloque de descarga en PDF';
$string['pdfexport:configure'] = 'Seleccionar los recursos publicados del curso';
$string['pdfexport:myaddinstance'] = 'Añadir PDF Export al área personal';
$string['pdfexport:view'] = 'Ver y descargar los recursos publicados';
$string['pluginname'] = 'Descarga de contenidos en PDF';
$string['privacy:metadata'] = 'El bloque de descarga en PDF no guarda datos personales vinculados a usuarios. Su configuración contiene los módulos publicados y la autoría y licencia opcionales del contenido, escritas por el profesorado como texto libre. Los PDF se generan bajo demanda y no se guardan.';
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
