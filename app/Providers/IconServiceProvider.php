<?php

namespace App\Providers;

use App\Support\Lucide;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the generated Lucide set with Filament.
 *
 * Filament's own icon registry accepts arbitrary markup, so Lucide is
 * registered directly rather than through an icon library Filament does not
 * already depend on. Nothing in vendor/ is touched.
 */
class IconServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $icons = [];

        foreach (Lucide::map() as $name => $svg) {
            $icons['lucide-'.$name] = new \Illuminate\Support\HtmlString($svg);
        }

        // Named to match the identifiers used across the enums and resources,
        // so a status badge and a navigation entry refer to the same icon.
        $icons['lucide-trash-2'] ??= $icons['lucide-trash'] ?? null;
        $icons['lucide-circle-help'] ??= $icons['lucide-circle-question-mark'] ?? null;

        FilamentIcon::register(array_filter($icons));
    }
}
