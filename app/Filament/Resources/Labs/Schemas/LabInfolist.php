<?php

namespace App\Filament\Resources\Labs\Schemas;

use App\Enums\LabActualState;
use App\Enums\LabOperationState;
use App\Models\Lab;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LabInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Control plane state')
                    ->description('What the portal intends this lab to be, and what the last operation reported.')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Lifecycle')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => strtoupper($state instanceof BackedEnum ? $state->value : (string) $state))
                            ->color(fn (mixed $state): string => match ((string) ($state instanceof BackedEnum ? $state->value : $state)) {
                                'ERROR' => 'danger',
                                'PROVISIONING' => 'warning',
                                'READY', 'RUNNING' => 'success',
                                'STOPPED' => 'gray',
                                default => 'gray',
                            }),
                        TextEntry::make('desired_state')
                            ->label('Desired state')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => strtoupper($state instanceof BackedEnum ? $state->value : (string) $state)),
                        TextEntry::make('actual_state')
                            ->label('Actual state')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => strtoupper($state instanceof BackedEnum ? $state->value : (string) $state))
                            ->color(fn (mixed $state): string => match ((string) ($state instanceof BackedEnum ? $state->value : $state)) {
                                LabActualState::Running->value => 'success',
                                LabActualState::Error->value => 'danger',
                                LabActualState::Absent->value, LabActualState::Missing->value => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('operation_state')
                            ->label('Last operation')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => strtoupper($state instanceof BackedEnum ? $state->value : (string) $state))
                            ->color(fn (mixed $state): string => match ((string) ($state instanceof BackedEnum ? $state->value : $state)) {
                                LabOperationState::Failed->value => 'danger',
                                LabOperationState::Queued->value,
                                LabOperationState::Provisioning->value,
                                LabOperationState::Starting->value,
                                LabOperationState::Stopping->value,
                                LabOperationState::Resetting->value,
                                LabOperationState::Deleting->value,
                                LabOperationState::Reconciling->value => 'warning',
                                LabOperationState::Idle->value => 'success',
                                default => 'gray',
                            }),
                    ]),

                Section::make('Failure detail')
                    ->description('Set when the most recent operation did not succeed.')
                    ->visible(fn (Lab $record): bool => $record->last_error_message !== null)
                    ->columns(1)
                    ->schema([
                        TextEntry::make('last_error_message')
                            ->label('Error')
                            ->color('danger')
                            ->columnSpanFull(),
                    ]),

                Section::make('Identity')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Lab name'),
                        TextEntry::make('container_name')->label('Container name'),
                        TextEntry::make('owner.name')->label('Owner'),
                        TextEntry::make('external_id')->label('External id')->placeholder('Not assigned'),
                        TextEntry::make('compute_host')->label('Compute host'),
                        TextEntry::make('base_image')->label('Base image'),
                        TextEntry::make('profile')->label('Incus profile'),
                    ]),

                Section::make('Network and access')
                    ->description('Populated only after the compute plane reports a real address and published port.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ip_address')
                            ->label('IP address')
                            ->placeholder('Not assigned')
                            ->copyable(),
                        TextEntry::make('network')->label('Network'),
                        TextEntry::make('access_host')
                            ->label('Access host')
                            ->placeholder('Not published')
                            ->copyable(),
                        TextEntry::make('access_username')
                            ->label('Access user')
                            ->placeholder('Not created'),
                        TextEntry::make('ssh_port')
                            ->label('SSH port')
                            ->placeholder('Not published'),
                        TextEntry::make('web_port')
                            ->label('Web port')
                            ->placeholder('Not published'),
                    ]),

                Section::make('Resource limits')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('cpu_limit')->label('CPU')->suffix(' core'),
                        TextEntry::make('memory_limit')->label('Memory')->suffix(' MB'),
                        TextEntry::make('storage_limit')->label('Storage')->suffix(' GB'),
                    ]),

                Section::make('Security')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('security_policy')->label('Policy'),
                        TextEntry::make('isolation_status')
                            ->label('Isolation status')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'enforced' => 'success',
                                'not_configured' => 'warning',
                                default => 'gray',
                            }),
                    ]),

                Section::make('SSH access')
                    ->description('Rendered from the verified values above. Shown only once the portal has confirmed them.')
                    ->visible(fn (Lab $record): bool => $record->sshCommand() !== null)
                    ->columns(1)
                    ->schema([
                        TextEntry::make('ssh_command')
                            ->label('Command')
                            ->state(fn (Lab $record): string => (string) $record->sshCommand())
                            ->fontFamily('mono')
                            ->color('teal')
                            ->columnSpanFull(),
                    ]),

                Section::make('Timestamps')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('access_verified_at')->label('Access verified')->placeholder('Never verified'),
                        TextEntry::make('last_verified_at')->label('State last verified')->placeholder('Never verified'),
                        TextEntry::make('created_at')->label('Created')->dateTime(),
                        TextEntry::make('updated_at')->label('Updated')->dateTime(),
                    ]),

                Section::make('Operations')
                    ->description('Every infrastructure operation requested for this lab.')
                    ->columns(1)
                    ->schema([
                        TextEntry::make('operations')
                            ->label('')
                            ->state(fn (Lab $record): string => $record->operations()->latest()->limit(8)->get()
                                ->map(fn ($operation): string => sprintf(
                                    '%s  %s  %s%s',
                                    $operation->type,
                                    strtoupper($operation->status->value),
                                    $operation->error_code ?? 'ok',
                                    $operation->finished_at ? '  '.$operation->finished_at->toDateTimeString() : '',
                                ))
                                ->implode(PHP_EOL))
                            ->placeholder('No operations requested yet')
                            ->fontFamily('mono')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
