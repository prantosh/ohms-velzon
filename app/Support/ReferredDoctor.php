<?php

namespace App\Support;

class ReferredDoctor
{
    /**
     * Referred doctor as printed on test reports. "Not Mentioned" only for
     * a blank entry or a placeholder -- a bare "Dr." prefix, "SELF", or the
     * two run together ("Dr.SELF", "SELFDr.", "DrSELF") as they actually
     * appear in invoices. Replaces an old "shorter than 8 characters" rule
     * that also hid genuine short names like "R. SAHA" or "T DAS".
     */
    public static function display(?string $name): string
    {
        $name = trim((string) $name);

        // Letters only, then any leading/trailing "DR" peeled off -- what's
        // left of a placeholder is either nothing or "SELF".
        $core = preg_replace('/[^A-Z]/', '', strtoupper($name));
        $core = preg_replace('/^DR|DR$/', '', $core);

        return ($core === '' || $core === 'SELF') ? 'Not Mentioned' : $name;
    }
}
