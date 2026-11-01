<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * The IMNEHNET wordmark.
 *
 * One wordmark, used by the sidebar, by the sign-in form and by the registration
 * form, because the previous arrangement had three places spelling the name and
 * two of them had drifted.
 *
 * The colours are written inline rather than as utility classes on purpose. A
 * wordmark has to render correctly wherever it is dropped, including on the
 * registration page before the panel's stylesheet has loaded, and a class that
 * silently does nothing is a worse outcome than a duplicated hex value. The
 * values mirror the --imnehnet-* tokens in theme.css; change them in both.
 *
 * Single line on purpose: the logo component in Filament wraps this in a box with
 * a fixed height, so a two-line mark would be clipped in the sidebar.
 */
final class Wordmark
{
    public static function make(string $tagline = null): HtmlString
    {
        return new HtmlString(sprintf(
            '<span style="display:inline-flex;align-items:center;gap:0.55rem;font-family:inherit">'
                .'<span aria-hidden="true" style="display:inline-block;width:0.5rem;height:0.5rem;border-radius:9999px;background:#14b8a6;box-shadow:0 0 0 3px rgba(20,184,166,0.18)"></span>'
                .'<span style="font-size:1rem;font-weight:700;letter-spacing:0.22em;color:#e8eaed;text-transform:uppercase;line-height:1">IMNEHNET</span>'
                .'</span>',
            $tagline ?? '',
        ));
    }

    /**
     * The line under the wordmark, shown on both forms.
     */
    public static function tagline(): string
    {
        return 'Cybersecurity Infrastructure Control Plane';
    }
}