<?php

namespace App\Services\Documents;

use Barryvdh\DomPDF\Facade\Pdf;

class Documents
{
    public function render(string $view, array $data)
    {
        $html = view($view, $data)->render();
        if (preg_match('/[\x{0900}-\x{097F}\x{0A80}-\x{0AFF}]/u', $html)) {
            return new UnicodePdf($html);
        }

        return Pdf::loadHTML($html)->setPaper('a4', 'portrait');
    }
}
