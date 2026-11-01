<?php

namespace App\Filament\Resources\Environments\Schemas;

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Models\LabEnvironment;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EnvironmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('container_name')
                            ->label('Container')
                            ->fontFamily('mono'),
                        TextEntry::make('status')
                            ->label('Lifecycle status')
                            ->badge()
                            ->formatStateUsing(fn (EnvironmentStatus $state): string => $state->label())
                            ->color(fn (EnvironmentStatus $state): string => $state->color()),
                        TextEntry::make('runtime_status')
                            ->label('Runtime status')
                            ->badge()
                            ->formatStateUsing(fn (RuntimeStatus $state): string => $state->label())
                            ->color(fn (RuntimeStatus $state): string => $state->color()),
                        TextEntry::make('created_at')->label('Created')->dateTime(),
                        TextEntry::make('updated_at')->label('Updated')->dateTime(),
                        TextEntry::make('mismatch')
                            ->label('Consistency')
                            ->state(fn (LabEnvironment $record): string => $record->hasStateMismatch()
                                ? 'STATE MISMATCH'
                                : 'Consistent')
                            ->badge()
                            ->color(fn (LabEnvironment $record): string => $record->hasStateMismatch()
                                ? 'warning'
                                : 'success'),
                    ]),

                Section::make('Compute')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('cpu_limit')
                            ->label('CPU limit')
                            ->formatStateUsing(fn (mixed $state): string => $state === null ? 'Not set' : sprintf('%s core(s)', $state)),
                        TextEntry::make('memory_limit_mb')
                            ->label('Memory limit')
                            ->formatStateUsing(fn (mixed $state): string => $state === null ? 'Not set' : sprintf('%d MiB', (int) $state)),
                        TextEntry::make('disk_limit_gb')
                            ->label('Storage limit')
                            // Not shown as a number unless the infrastructure
                            // actually enforces one. A quota that is accepted
                            // and ignored is worse than an honest gap.
                            ->formatStateUsing(fn (mixed $state): string => $state === null
                                ? 'Not enforced by the infrastructure'
                                : sprintf('%d GB', (int) $state)),
                        TextEntry::make('image')->label('Image')->fontFamily('mono'),
                    ]),

                Section::make('Network')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('internal_ip')
                            ->label('Actual IP address')
                            ->placeholder('Not observed yet')
                            ->fontFamily('mono'),
                        TextEntry::make('incus_remote')->label('Incus remote'),
                        TextEntry::make('incus_project')->label('Incus project'),
                    ]),

                Section::make('Automation')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('tofu_workspace')
                            ->label('OpenTofu workspace')
                            ->placeholder('Not assigned')
                            ->fontFamily('mono'),
                        TextEntry::make('provisioned_at')
                            ->label('Provisioned at')
                            ->placeholder('Never')
                            ->dateTime(),
                        TextEntry::make('last_error')
                            ->label('Last error')
                            ->placeholder('None')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
