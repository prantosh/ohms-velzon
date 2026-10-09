<?php

namespace App\Services;

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * dompdf has no password-protection support of its own -- this re-imports
 * an already-rendered dompdf PDF page-by-page into a TCPDF document (via
 * FPDI) purely to apply TCPDF's encryption, then outputs the result. Pages
 * are copied as opaque templates (not re-parsed/re-rendered), so layout,
 * fonts and embedded images come through unchanged.
 */
class PdfPasswordProtectionService
{
    /**
     * @param string $pdfContent raw PDF bytes, e.g. from $pdf->output()
     * @param string $openPassword required to open the file in any PDF
     *     reader. A random owner password is generated so recipients can't
     *     accidentally land in "owner mode" (no restrictions) using the
     *     same password -- it's never shared, it only exists to drive the
     *     permission bits.
     * @return string encrypted PDF bytes
     */
    public static function protect(string $pdfContent, string $openPassword): string
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'pdfprotect_') . '.pdf';
        file_put_contents($sourcePath, $pdfContent);

        try {

            $pdf = new Fpdi('P', 'pt');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            $pageCount = $pdf->setSourceFile($sourcePath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {

                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }

            // Printing allowed (staff/patients still need to print the
            // report), copying/modifying not -- owner password is random
            // and discarded since nobody needs to log in as the "owner".
            $pdf->SetProtection(['print'], $openPassword, bin2hex(random_bytes(16)));

            return $pdf->Output('', 'S');

        } finally {

            @unlink($sourcePath);
        }
    }

    /**
     * Last 4 digits of the mobile number, the agreed-on password scheme for
     * every diagnostic report sent via WhatsApp. Returns null if there
     * aren't at least 4 digits to use (no mobile on file, bad data) --
     * callers should send the report unprotected rather than lock patients
     * out with an unguessable password.
     */
    public static function passwordForMobile(?string $mobileNo): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $mobileNo);

        return strlen($digits) >= 4 ? substr($digits, -4) : null;
    }

    /**
     * The approved WATI report template can't be edited, so the password
     * hint rides inside its {{3}} (invoice number) variable, which reads
     * "...against invoice number INV-1 (PDF password: ...) is ready".
     */
    public static function invoiceNoWithHint(string $invoiceNo, bool $protected): string
    {
        return $protected
            ? $invoiceNo . ' (Report Unlocking Password: last 4 digits of your mobile number)'
            : $invoiceNo;
    }
}
