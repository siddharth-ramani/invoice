<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\Storage;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\HeaderUtils;

class UnicodePdf
{
    private Mpdf $pdf;

    private ?string $bytes = null;

    public function __construct(string $html)
    {
        Storage::disk('local')->makeDirectory('private/pdf-cache');
        $fontDir = public_path('assets/fonts/noto');
        $defaults = (new ConfigVariables)->getDefaults();
        $fonts = (new FontVariables)->getDefaults();
        $this->pdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'tempDir' => Storage::disk('local')->path('private/pdf-cache'), 'fontDir' => array_merge($defaults['fontDir'], [$fontDir]), 'fontdata' => $fonts['fontdata'] + ['gujaratiprint' => ['R' => 'FreeSerif.ttf', 'B' => 'FreeSerif.ttf', 'I' => 'FreeSerif.ttf', 'BI' => 'FreeSerif.ttf', 'useOTL' => 0xFF]], 'default_font' => 'dejavusans']);
        $this->pdf->shrink_tables_to_fit = 1;
        // mPDF does not interpret the Dompdf @page size/margin shorthand reliably.
        // Load the shared print rules locally and keep the explicit A4 margins.
        if (str_contains($html, 'assets/css/receipt.css')) {
            $css = preg_replace('/@page\s*\{[^}]*\}/s', '', file_get_contents(public_path('assets/css/receipt.css')));
            $this->pdf->WriteHTML($css, HTMLParserMode::HEADER_CSS);
            $html = preg_replace('/<link\b[^>]*>/i', '', $html);
        }
        $this->pdf->WriteHTML(file_get_contents(public_path('assets/css/indic-print.css')), HTMLParserMode::HEADER_CSS);
        $this->pdf->WriteHTML($this->shapeRuns($html));
    }

    private function shapeRuns(string $html): string
    {
        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        $xpath = new \DOMXPath($doc);
        $nodes = [];
        foreach ($xpath->query('//text()[not(ancestor::style) and not(ancestor::script)]') as $node) {
            $nodes[] = $node;
        }
        foreach ($nodes as $node) {
            $parts = preg_split('/([\x{0A80}-\x{0AFF}\x{200C}\x{200D}]+(?:\s+[\x{0A80}-\x{0AFF}\x{200C}\x{200D}]+)*|[\x{0900}-\x{097F}\x{200C}\x{200D}]+(?:\s+[\x{0900}-\x{097F}\x{200C}\x{200D}]+)*)/u', $node->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
            if (count($parts) < 2) {
                continue;
            }$fragment = $doc->createDocumentFragment();
            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }if (preg_match('/[\x{0A80}-\x{0AFF}]/u', $part)) {
                    $element = $doc->createElement('span');
                    $element->setAttribute('class', 'pdf-gu');
                    $element->setAttribute('lang', 'gu');
                    $element->appendChild($doc->createTextNode($part));
                    $fragment->appendChild($element);
                } elseif (preg_match('/[\x{0900}-\x{097F}]/u', $part)) {
                    $element = $doc->createElement('span');
                    $element->setAttribute('class', 'pdf-hi');
                    $element->setAttribute('lang', 'hi');
                    $element->appendChild($doc->createTextNode($part));
                    $fragment->appendChild($element);
                } else {
                    $fragment->appendChild($doc->createTextNode($part));
                }
            }$node->parentNode->replaceChild($fragment, $node);
        }
        $result = $doc->saveHTML();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return preg_replace('/<\?xml[^>]*>\s*/', '', $result);
    }

    public function output(): string
    {
        return $this->bytes ??= $this->pdf->Output('', Destination::STRING_RETURN);
    }

    public function download(string $name)
    {
        return response($this->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $name, 'document.pdf')]);
    }

    public function stream(string $name)
    {
        return response($this->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => HeaderUtils::makeDisposition('inline', $name, 'document.pdf')]);
    }

    public function getPageCount(): int
    {
        return $this->pdf->page;
    }
}
