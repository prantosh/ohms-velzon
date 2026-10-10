<?php

namespace App\Support;

use Barryvdh\DomPDF\PDF;

/**
 * Stamps "Page 1/2" on every page of a dompdf document. Done on the canvas
 * after render() instead of via an inline <script type="text/php"> in each
 * Blade view, because that needs dompdf's enable_php option on -- i.e.
 * executing PHP embedded in report HTML, which includes doctor-entered
 * findings. Returns the same PDF object so the caller's existing
 * ->stream() / ->output() / ->save() carries on unchanged (they don't
 * re-render once render() has run).
 *
 * Defaults suit the 60mm-bottom-margin letterhead layouts: the bottom band
 * is the (pre-printed or image) footer art -- a dark address bar across its
 * lower ~30% -- so the number sits at the left margin in the plain upper
 * part of that band, clear of the signature and the address bar.
 */
class PdfPageNumbers
{
    public static function add(PDF $pdf, float $fromBottom = 110, string $align = 'left'): PDF
    {
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();

        $font = $fontMetrics->getFont('Helvetica', 'normal');
        $size = 8;

        $x = 40;

        $textWidth = $fontMetrics->getTextWidth('Page 00/00', $font, $size);

        if ($align === 'center') {
            $x = ($canvas->get_width() - $textWidth) / 2;
        } elseif ($align === 'right') {
            $x = $canvas->get_width() - 40 - $textWidth;
        }

        $canvas->page_text($x, $canvas->get_height() - $fromBottom, 'Page {PAGE_NUM}/{PAGE_COUNT}', $font, $size, [0.3, 0.3, 0.3]);

        return $pdf;
    }
}
