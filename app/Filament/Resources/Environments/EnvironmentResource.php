<?php

namespace App\Filament\Resources\Environments;

use App\Filament\Resources\Environments\Pages\CreateEnvironment;
use App\Filament\Resources\Environments\Pages\ListEnvironments;
use App\Filament\Resources\Environments\Pages\ViewEnvironment;
use App\Filament\Resources\Environments\Schemas\EnvironmentInfolist;
use App\Filament\Resources\Environments\Tables\EnvironmentsTable;
use App\Models\LabEnvironment;
use App\Support\Lucide;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A student lab: one Linux system container inside Incus on the compute
 * plane. LAB-ENV itself is never shown as an environment.
 */
class EnvironmentResource extends Resource
{
    protected static ?string $model = LabEnvironment::class;

    protected static ?string $recordTitleAttribute = 'container_name';

    protected static ?string $label = 'Environment';

    protected static ?string $pluralLabel = 'Environments';

    protected static string|\UnitEnum|null $navigationGroup = 'Environments';

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return EnvironmentsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EnvironmentInfolist::configure($schema);
    }

    /**
     * There is no edit form. A lifecycle change is an operation against the
     * infrastructure, not an edit of a row, so editing fields by hand would
     * put the record out of step with what actually exists.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    /**
     * Resolved as markup rather than a name. Filament looks a name up in the
     * blade-icons default set, which only ships Heroicons, so a Lucide name
     * would raise SvgNotFound and take the whole page down.
     */
    public static function getNavigationIcon(): Htmlable|string|null
    {
        return Lucide::icon('server');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEnvironments::route('/'),
            'create' => CreateEnvironment::route('/create'),
            'view' => ViewEnvironment::route('/{record}'),
        ];
    }

    /**
     * The specification's sidebar lists Create Environment as its own entry
     * under Environments, beside All Environments.
     *
     * It links to the resource's create page rather than repeating the form, so
     * there is one create path in the application instead of two that could
     * drift apart in their validation.
     *
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        $items = parent::getNavigationItems();

        $create = NavigationItem::make('Create Environment')
            ->url(static::getUrl('create'))
            ->icon(Lucide::icon('plus'))
            ->visible(fn (): bool => static::canCreate());

        // Straight after the list, which is where an operator expects to reach
        // it from: the two belong to the same group and are used together.
        array_splice($items, 1, 0, [$create]);

        return $items;
    }
}
