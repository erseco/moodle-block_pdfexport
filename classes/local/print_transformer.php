<?php
// HTML-to-print adapter. No requests to external hosts are made.
namespace block_pdfexport\local;
defined('MOODLE_INTERNAL') || die();

final class print_transformer {
    private array $temporary = [];
    private array $warnings = [];

    public function warnings(): array { return array_values(array_unique($this->warnings)); }
    public function cleanup(): void {
        foreach ($this->temporary as $path) { if (is_file($path)) { @unlink($path); } }
        $this->temporary = [];
    }

    public function transform(string $html, \context_module $context, string $component, string $area,
            int $itemid): string {
        if (!class_exists('DOMDocument')) {
            $this->warnings[] = 'La extensión DOM de PHP no está disponible.';
            return strip_tags($html, '<h1><h2><h3><h4><p><br><b><strong><i><em><ul><ol><li><a><table><tr><td><th>');
        }
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="pdfexport-root">' . $html . '</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);
        $root = $xpath->query('//*[@id="pdfexport-root"]')->item(0);
        if (!$root) { return ''; }
        // Remove executable content. CSS/JS can never be trusted to provide print content.
        foreach (['script','style','form','input','select','textarea','button','object','embed','canvas','svg'] as $tag) {
            foreach (iterator_to_array($root->getElementsByTagName($tag)) as $node) {
                if (in_array($tag, ['canvas','svg','object','embed'], true)) { $this->warnings[] = 'Se omitió un elemento visual o interactivo no convertible.'; }
                $node->parentNode?->removeChild($node);
            }
        }
        // Explicitly render content of native details elements, even if originally closed.
        foreach (iterator_to_array($root->getElementsByTagName('details')) as $details) {
            $details->removeAttribute('hidden');
            $details->removeAttribute('style');
            $details->setAttribute('open', 'open');
            foreach (iterator_to_array($details->getElementsByTagName('summary')) as $summary) {
                $heading = $dom->createElement('h4');
                while ($summary->firstChild) { $heading->appendChild($summary->firstChild); }
                $summary->parentNode->replaceChild($heading, $summary);
            }
            $wrapper = $dom->createElement('div');
            while ($details->firstChild) { $wrapper->appendChild($details->firstChild); }
            $details->parentNode->replaceChild($wrapper, $details);
        }
        // Represent embedded media as named links instead of silently discarding it.
        foreach (['iframe', 'video', 'audio'] as $tag) {
            foreach (iterator_to_array($root->getElementsByTagName($tag)) as $media) {
                $src = $media->getAttribute('src');
                if (!$src) {
                    $sources = $media->getElementsByTagName('source');
                    if ($sources->length) { $src = $sources->item(0)->getAttribute('src'); }
                }
                $p = $dom->createElement('p');
                $p->appendChild($dom->createTextNode('Multimedia: '));
                if (preg_match('~^https?://~i', $src)) {
                    $a = $dom->createElement('a', 'Ver contenido original');
                    $a->setAttribute('href', $src);
                    $p->appendChild($a);
                } else { $p->appendChild($dom->createTextNode('consultar el recurso en Moodle')); }
                $media->parentNode->replaceChild($p, $media);
            }
        }
        foreach (iterator_to_array($root->getElementsByTagName('img')) as $img) {
            $src = $img->getAttribute('src');
            $file = $this->resolve_file($src, $context, $component, $area, $itemid);
            if ($file && $file->get_filesize() <= 12 * 1024 * 1024) {
                $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
                // TCPDF handles JPEG and PNG predictably; leave other formats explicit.
                if (in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
                    $tmp = tempnam(make_temp_directory('block_pdfexport'), 'img_');
                    if ($tmp && $file->copy_content_to($tmp)) {
                        $this->temporary[] = $tmp;
                        $img->setAttribute('src', $tmp);
                        $img->setAttribute('width', '450');
                        $img->removeAttribute('height');
                        continue;
                    }
                }
            }
            $p = $dom->createElement('p');
            $p->appendChild($dom->createTextNode('Imagen no incorporada: ' . ($img->getAttribute('alt') ?: 'sin descripción')));
            $img->parentNode->replaceChild($p, $img);
            $this->warnings[] = 'Algunas imágenes no se incorporaron; comprueba sus formatos y permisos.';
        }
        // Strip unsafe or browser-only presentation attributes, and forbid remote fetches.
        foreach (iterator_to_array($xpath->query('.//*', $root)) as $element) {
            foreach (iterator_to_array($element->attributes ?? []) as $attr) {
                $name = strtolower($attr->name);
                if (str_starts_with($name, 'on') || in_array($name, ['style', 'class', 'hidden', 'aria-hidden', 'srcset'], true)) {
                    $element->removeAttributeNode($attr);
                }
            }
            if ($element->hasAttribute('href') && !preg_match('~^(https?://|mailto:|#)~i', $element->getAttribute('href'))) {
                $element->removeAttribute('href');
            }
        }
        $output = '';
        foreach ($root->childNodes as $child) { $output .= $dom->saveHTML($child); }
        return $output;
    }

    private function resolve_file(string $src, \context_module $context, string $component,
            string $area, int $itemid): ?\stored_file {
        global $CFG;
        $path = parse_url(html_entity_decode($src), PHP_URL_PATH);
        if (!is_string($path)) { return null; }
        $prefix = parse_url($CFG->wwwroot, PHP_URL_PATH) ?: '';
        $marker = rtrim($prefix, '/') . '/pluginfile.php/' . $context->id . '/' . $component . '/' . $area . '/';
        $position = strpos($path, $marker);
        if ($position === false) { return null; }
        $relative = rawurldecode(substr($path, $position + strlen($marker)));
        if ($component === 'mod_book') {
            if (!str_starts_with($relative, $itemid . '/')) { return null; }
            $relative = substr($relative, strlen((string)$itemid) + 1);
        } else if (str_starts_with($relative, '0/')) {
            $relative = substr($relative, 2);
        }
        $relative = ltrim($relative, '/');
        $name = basename($relative);
        $filepath = '/' . trim(str_replace('\\', '/', dirname($relative)), './') . '/';
        if ($filepath === '//') { $filepath = '/'; }
        $file = get_file_storage()->get_file($context->id, $component, $area, $itemid, $filepath, $name);
        return $file && !$file->is_directory() ? $file : null;
    }
}
