<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitizes rich-text HTML from CKEditor fields (USG Report Template /
 * USG Report clinical_history, findings, impression) before it's persisted.
 * The editor's toolbar only ever produces a small, known tag set -- this is
 * defense-in-depth against a malicious request bypassing the UI entirely.
 */
class HtmlSanitizerService
{
    public static function sanitizeClinicalText(?string $html): ?string
    {
        if ($html === null || trim(strip_tags($html)) === '') {
            return null;
        }

        $config = HTMLPurifier_Config::createDefault();
        // p[style] carries CKEditor's Alignment feature (text-align only,
        // restricted below); span[class] carries its FontSize feature,
        // which uses named classes (text-tiny/small/big/huge) rather than
        // arbitrary inline sizes.
        $config->set('HTML.Allowed', 'p[style],span[class],strong,em,u,ul,ol,li,br');
        $config->set('CSS.AllowedProperties', 'text-align');
        // No cache directory needed for an allowlist this small, and
        // production has no way to create/chmod one (FTP-only deploy).
        $config->set('Cache.DefinitionImpl', null);

        return (new HTMLPurifier($config))->purify($html);
    }
}
