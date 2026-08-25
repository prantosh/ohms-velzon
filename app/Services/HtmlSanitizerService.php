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
        // p[style] carries CKEditor's Alignment feature (text-align) and
        // Indent feature (margin-left/margin-right, both restricted below);
        // span[class] carries its FontSize feature, which uses named
        // classes (text-tiny/small/big/huge) rather than arbitrary inline
        // sizes; span[style] carries its FontColor feature (color only,
        // restricted below); h2/h3/h4[style] carry its Heading feature
        // (section titles) plus the same Alignment/Indent styles a heading
        // can also carry. table/thead/tbody/tr/th/td carry its Table
        // feature -- TableProperties/TableCellProperties style borders,
        // background and cell alignment via inline style on table/th/td,
        // restricted to the table-relevant CSS properties below. CKEditor
        // also wraps the table in a <figure> for its own editing UI, but
        // HTMLPurifier's HTML4-based definition doesn't recognize that
        // element at all -- it's stripped either way, so it's left out of
        // the allowlist to avoid the warning noise.
        $config->set(
            'HTML.Allowed',
            'p[style],span[class],span[style],h2[style],h3[style],h4[style],strong,em,u,ul,ol,li,br,'
                . 'table[style],thead,tbody,'
                . 'tr,th[style|scope|colspan|rowspan],td[style|colspan|rowspan]'
        );
        $config->set(
            'CSS.AllowedProperties',
            'text-align,vertical-align,border,border-color,border-style,border-width,'
                . 'background-color,width,height,padding,border-collapse,float,color,'
                . 'margin-left,margin-right'
        );
        // No cache directory needed for an allowlist this small, and
        // production has no way to create/chmod one (FTP-only deploy).
        $config->set('Cache.DefinitionImpl', null);

        return (new HTMLPurifier($config))->purify($html);
    }
}
