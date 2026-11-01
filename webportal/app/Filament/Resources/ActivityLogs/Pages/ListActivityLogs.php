<?php

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Models\ActivityLog;
use App\Services\ActivityLogExcelWriter;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->button()
                ->schema([
                    Select::make('format')
                        ->label('Format file')
                        ->options([
                            'xlsx' => 'Excel (.xlsx)',
                            'csv' => 'CSV (.csv)',
                        ])
                        ->default('xlsx')
                        ->required(),
                ])
                ->action(function (array $data, Request $request) {
                    abort_unless(auth()->user()?->isAdmin() === true, 403);

                    $format = $data['format'] ?? 'xlsx';
                    abort_unless(in_array($format, ['xlsx', 'csv'], true), 422);

                    $query = ActivityLog::query()
                        ->with('actor')
                        ->when(filled($this->tableSearch ?? null), function (Builder $query): void {
                            $search = $this->tableSearch;

                            $query->where(function (Builder $query) use ($search): void {
                                $query
                                    ->where('action', 'like', "%{$search}%")
                                    ->orWhere('description', 'like', "%{$search}%")
                                    ->orWhereHas('actor', function (Builder $actorQuery) use ($search): void {
                                        $actorQuery->where('name', 'like', "%{$search}%");
                                    });
                            });
                        })
                        ->orderByDesc('created_at');

                    $statuses = data_get($this->tableFilters, 'status.values', []);

                    if (filled($statuses)) {
                        $query->whereIn('status', (array) $statuses);
                    }

                    try {
                        $filePath = app(ActivityLogExcelWriter::class)->write($query, $format);
                        $fileName = basename($filePath);

                        // Catat aktivitas export ke activity log.
                        ActivityLog::create([
                            'actor_id' => auth()->id(),
                            'action' => 'EXPORT_ACTIVITY_LOGS',
                            'resource_type' => ActivityLog::class,
                            'status' => 'completed',
                            'description' => "Ekspor log aktivitas ke format {$format} ({$fileName}).",
                            'ip_address' => $request->ip(),
                        ]);

                        // Notifikasi sukses sebelum file dikirim.
                        Notification::make()
                            ->title('Export berhasil')
                            ->body("Berkas {$fileName} berhasil diunduh.")
                            ->success()
                            ->send();

                        return response()->download($filePath, $fileName)->deleteFileAfterSend(true);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Ekspor gagal')
                            ->body($e->getMessage() ?: 'Terjadi kesalahan saat membuat berkas ekspor.')
                            ->danger()
                            ->send();

                        return null;
                    }
                }),
        ];
    }
}
