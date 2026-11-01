<?php

namespace App\Filament\Resources\Environments;

use App\Filament\Resources\Environments\Pages\CreateEnvironment;
use App\Filament\Resources\Environments\Pages\ListEnvironments;
use App\Filament\Resources\Environments\Pages\ViewEnvironment;
use App\Filament\Resources\Environments\Schemas\EnvironmentInfolist;
use App\Filament\Resources\Environments\Tables\EnvironmentsTable;
use App\Models\LabEnvironment;
use App\Support\Lucide;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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

    protected static string|\UnitEnum|null $navigationGroup = 'LAB MANAGEMENT';

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
     * Everyone sees their own environments; an administrator sees all of them.
     *
     * Scoped in the query rather than filtered in the table, because a row that
     * was fetched and then hidden has still been read. The owner check belongs
     * here as well as in the policy: this is what stops the record existing in
     * front of someone at all, and the policy is what stops them acting on it if
     * they reach it another way.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user === null) {
            // Nobody is signed in, so nothing is visible. Returning an impossible
            // condition rather than an empty query keeps this a query rather than
            // an early return, so a later caller cannot forget the check.
            return $query->whereRaw('1 = 0');
        }

        return $user->isAdmin() ? $query : $query->where('user_id', $user->getKey());
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['container_name', 'internal_ip', 'status'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'IP' => (string) ($record->internal_ip ?? '—'),
            'Status' => (string) ($record->status?->value ?? $record->status ?? 'Unknown'),
        ];
    }

    /**
     * Paired labs must be created exclusively through My Labs.
     */
    public static function canCreate(): bool
    {
        return false;
    }
}
