<?php

namespace App\Support;

use Barryvdh\DomPDF\PDF;

/**
 * Stamps a large diagonal translucent watermark (e.g. "NOT CONFIRMED") across
 * the centre of every page of a dompdf document. Drawn on the canvas after
 * render() -- like PdfPageNumbers -- so no HTML/CSS or embedded PHP is
 * needed in the report views. Call it AFTER PdfPageNumbers::add() (or on its
 * own); both are safe on an already-rendered PDF.
 */
class PdfWatermark
{
    public static function add(PDF $pdf, string $text = 'NOT CONFIRMED'): PDF
    {
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();

        $font = $fontMetrics->getFont('Helvetica', 'bold');
        $size = 70;

        $width = $fontMetrics->getTextWidth($text, $font, $size);

        // Rotate 45deg about the page centre: the text's start point sits
        // half its width back along the diagonal from the centre.
        $cx = $canvas->get_width() / 2;
        $cy = $canvas->get_height() / 2;
        $half = ($width / 2) * M_SQRT1_2;

        $x = $cx - $half;
        $y = $cy + $half + ($size / 3);

        $canvas->page_script(function ($pageNumber, $pageCount, $c) use ($text, $font, $size, $x, $y) {
            $c->set_opacity(0.18);
            $c->text($x, $y, $text, $font, $size, [0.85, 0.05, 0.05], 0.0, 0.0, -45); // dompdf's canvas angle is clockwise-positive; -45 rises to the right
            $c->set_opacity(1.0);
        });

        return $pdf;
    }
}
