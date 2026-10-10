<?php

namespace App\Support;

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Joins several finished PDFs into one, page by page (FPDI imports each page
 * as an opaque template, so layout, fonts and images come through
 * unchanged -- the same approach PdfPasswordProtectionService uses). The
 * free FPDI parser can't read every PDF (e.g. some compressed-xref files
 * from other software); that surfaces as an exception for the caller to
 * handle.
 */
class PdfMerger
{
    /**
     * @param string[] $pdfContents raw PDF bytes
     */
    public static function merge(array $pdfContents): string
    {
        if (count($pdfContents) === 1) {
            return $pdfContents[0];
        }

        $paths = [];

        try {

            $pdf = new Fpdi('P', 'pt');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            foreach ($pdfContents as $content) {

                $path = tempnam(sys_get_temp_dir(), 'pdfmerge_') . '.pdf';
                file_put_contents($path, $content);
                $paths[] = $path;

                $pageCount = $pdf->setSourceFile($path);

                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {

                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);

                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);
                }
            }

            return $pdf->Output('', 'S');

        } finally {

            foreach ($paths as $path) {
                @unlink($path);
            }
        }
    }
}
