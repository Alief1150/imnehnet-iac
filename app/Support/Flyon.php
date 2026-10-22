<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Flyon UI icons, inlined so a page never waits on an icon font.
 *
 * The set mirrors the Flyon UI shape language: a single weight stroke, round
 * caps and joins, and no fill, so an icon inherits the colour of whatever
 * control holds it. Every glyph is 24 by 24 and strokes with currentColor,
 * which is what makes one helper usable across light and dark surfaces.
 *
 * Names are the Flyon UI names, so a glyph can be swapped for the packaged
 * component later without touching the calling code.
 */
final class Flyon
{
    /**
     * @return array<string, string>
     */
    public static function map(): array
    {
        return [
            'shield-alert' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-fi="shield-alert" class="fi-fi"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
SVG,
            'shield-check' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-fi="shield-check" class="fi-fi"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
SVG,
            'shield' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-fi="shield" class="fi-fi"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
SVG,
            'activity' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-fi="activity" class="fi-fi"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
SVG,
            'users' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-fi="users" class="fi-fi"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
SVG,
            'chart-bar' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-fi="chart-bar" class="fi-fi"><path d="M3 3v18h18"/><path d="M7 16v-4"/><path d="M12 16V8"/><path d="M17 16v-7"/></svg>
SVG,
            'chart-line' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-fi="chart-line" class="fi-fi"><path d="M3 3v18h18"/><path d="m7 14 4-4 3 3 5-6"/></svg>
SVG,
            'server' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-fi="server" class="fi-fi"><rect x="2" y="3" width="20" height="8" rx="2"/><rect x="2" y="13" width="20" height="8" rx="2"/><path d="M6 7h.01"/><path d="M6 17h.01"/></svg>
SVG,
            'eye' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-fi="eye" class="fi-fi"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
SVG,
            'clock' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-fi="clock" class="fi-fi"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
SVG,
            'globe' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-fi="globe" class="fi-fi"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18z"/></svg>
SVG,
            'filter' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-fi="filter" class="fi-fi"><path d="M3 5h18l-7 8v6l-4 2v-8z"/></svg>
SVG,
            'external-link' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-fi="external-link" class="fi-fi"><path d="M14 3h7v7"/><path d="M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>
SVG,
            'info' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-fi="info" class="fi-fi"><circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/></svg>
SVG,
            'chevron-down' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-fi="chevron-down" class="fi-fi"><path d="m6 9 6 6 6-6"/></svg>
SVG,
            'check-circle' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-fi="check-circle" class="fi-fi"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 5-5"/></svg>
SVG,
            'x-circle' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-fi="x-circle" class="fi-fi"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6"/><path d="m15 9-6 6"/></svg>
SVG,
        ];
    }

    /**
     * The markup for one icon, or a neutral glyph when the name is unknown.
     */
    public static function svg(string $name, string $class = ''): string
    {
        $svg = self::map()[$name] ?? null;

        if ($svg === null) {
            $svg = self::map()['info'];
        }

        if ($class === '') {
            return $svg;
        }

        return str_replace('class="fi-fi"', 'class="fi-fi '.$class.'"', $svg);
    }

    public static function icon(string $name, string $class = ''): ?HtmlString
    {
        $svg = self::map()[$name] ?? null;

        return $svg === null ? null : new HtmlString(self::svg($name, $class));
    }

    /**
     * An icon as markup, always, falling back to a neutral glyph.
     *
     * For the callers that are rendering a control whose presence is not in question.
     */
    public static function html(string $name, string $class = ''): HtmlString
    {
        return new HtmlString(self::svg($name, $class));
    }

    /**
     * Whether a name is available, so a caller can hide a control rather than
     * render an icon that means nothing.
     */
    public static function has(string $name): bool
    {
        return array_key_exists($name, self::map());
    }
}
