<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Pages\ViewActivityLog;
use App\Filament\Resources\ActivityLogs\Schemas\ActivityLogForm;
use App\Filament\Resources\ActivityLogs\Schemas\ActivityLogInfolist;
use App\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use App\Models\ActivityLog;
use App\Support\Lucide;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class ActivityLogResource extends Resource
{
    protected static ?string $recordTitleAttribute = 'action';

    protected static ?string $model = ActivityLog::class;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function form(Schema $schema): Schema
    {
        return ActivityLogForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ActivityLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivityLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
            'view' => ViewActivityLog::route('/{record}'),
        ];
    }

    public static function getNavigationIcon(): Htmlable|string|null
    {
        return Lucide::icon('scroll-text');
    }

    protected static string|\UnitEnum|null $navigationGroup = 'OPERATIONS';

    protected static ?int $navigationSort = 30;

    protected static ?string $label = 'Activity Logs';

    protected static ?string $pluralLabel = 'Activity Logs';

    public static function getGloballySearchableAttributes(): array
    {
        return ['action', 'description', 'resource_type', 'resource_id'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Details' => (string) ($record->description ?? ''),
            'Time' => $record->created_at?->format('Y-m-d H:i:s') ?? '—',
        ];
    }
}
