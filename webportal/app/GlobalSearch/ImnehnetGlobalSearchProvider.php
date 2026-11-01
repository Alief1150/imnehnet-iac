<?php

namespace App\GlobalSearch;

use Filament\Facades\Filament;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\GlobalSearch\GlobalSearchResults;
use Filament\GlobalSearch\Providers\DefaultGlobalSearchProvider;
use Illuminate\Support\Str;

class ImnehnetGlobalSearchProvider extends DefaultGlobalSearchProvider
{
    public function getResults(string $query): ?GlobalSearchResults
    {
        $results = parent::getResults($query);

        if ($results === null) {
            return null;
        }

        $query = trim($query);

        if ($query === '') {
            return $results;
        }

        $pages = [];

        foreach (Filament::getPages() as $page) {
            if (! $page::canAccess()) {
                continue;
            }

            $label = (string) $page::getNavigationLabel();
            $className = class_basename($page);
            $slug = Str::kebab($className);

            $searchableText = implode(' ', [
                $label,
                $className,
                $slug,
            ]);

            if (! Str::contains(
                Str::lower($searchableText),
                Str::lower($query)
            )) {
                continue;
            }

            $pages[] = new GlobalSearchResult(
                title: $label,
                url: $page::getUrl(),
                details: [
                    'Type' => 'Page',
                ],
            );

            if (count($pages) >= 12) {
                break;
            }
        }

        if ($pages !== []) {
            $results->category('Pages', $pages);
        }

        return $results;
    }
}
