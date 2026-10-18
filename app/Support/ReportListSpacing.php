<?php

namespace App\Support;

/**
 * Fixes a print-CSS side effect in the free-text report PDFs (USG,
 * Cardiology, Non-Pathology): `.report-section-body li p { display:inline }`
 * merges every <p> inside a list item onto one visual line, so a bold
 * "Header:" paragraph sits next to its "content" paragraph as intended --
 * but it just as happily merges two UNRELATED sentences onto one line
 * whenever the admin typed a blank paragraph between them to separate them
 * (the same blank paragraph that, elsewhere in the same list item, is
 * meant to disappear so a trailing blank line doesn't leave a visible gap).
 *
 * CSS alone can't tell those two cases apart -- a blank paragraph still has
 * an nbsp text node, so it isn't :empty. This walks the actual paragraphs
 * instead: a blank paragraph is dropped entirely (it was only ever spacing),
 * and a content paragraph that had a blank paragraph typed before it gets an
 * explicit <br> so it still starts its own print line, while content
 * paragraphs typed back-to-back with no blank between them keep merging
 * onto one line exactly as before.
 */
class ReportListSpacing
{
    public static function fixListParagraphBreaks(?string $html): string
    {
        if ($html === null || $html === '' || stripos($html, '<li') === false) {
            return (string) $html;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div id="__root__">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $root = $dom->getElementById('__root__');
        if (!$root) {
            return $html;
        }

        $xpath = new \DOMXPath($dom);

        foreach (iterator_to_array($xpath->query('//li')) as $li) {
            $paragraphs = iterator_to_array($xpath->query('./p', $li));
            if (count($paragraphs) < 2) {
                continue;
            }

            $sawBlank = false;
            $sawContent = false;

            foreach ($paragraphs as $p) {
                $text = str_replace("\u{00A0}", ' ', $p->textContent);
                $isBlank = trim($text) === '';

                if ($isBlank) {
                    $p->parentNode->removeChild($p);
                    $sawBlank = true;
                    continue;
                }

                if ($sawContent && $sawBlank) {
                    $p->parentNode->insertBefore($dom->createElement('br'), $p);
                }

                $sawContent = true;
                $sawBlank = false;
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out;
    }
}
