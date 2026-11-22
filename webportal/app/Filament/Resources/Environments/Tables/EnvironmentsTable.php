<?php

namespace App\Filament\Resources\Environments\Tables;

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Models\LabEnvironment;
use App\Models\AdminEnvironmentPair;
use App\Services\AdminEnvironmentPairDeleter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Support\Lucide;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The operational view of every environment.
 *
 * Reads densely and in a fixed order, because an operator scans it rather than
 * reads it: identity, where it lives, what it is made of, what state each side
 * of the split reports, and then the actions that state actually allows.
 *
 * Lifecycle status and runtime status are two separate columns. An environment
 * can be ready in the lifecycle sense and stopped as a container, and
 * collapsing them would make that impossible to see.
 */
class EnvironmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('container_name')
                    // IMNEHNET_PAIR_BORDER_V3
                    ->formatStateUsing(function ($state): \Illuminate\Support\HtmlString {
                        $css = <<<'CSS'
tr.imnehnet-pair-admin-attacker > td,
tr.imnehnet-pair-admin-victim > td {
    background-color: rgba(20,184,166,.035);
}
tr.imnehnet-pair-student-attacker > td,
tr.imnehnet-pair-student-victim > td {
    background-color: rgba(96,165,250,.035);
}
tr.imnehnet-pair-admin-attacker > td {
    border-top: 1px solid #14b8a6 !important;
}
tr.imnehnet-pair-admin-victim > td {
    border-bottom: 1px solid #14b8a6 !important;
}
tr.imnehnet-pair-student-attacker > td {
    border-top: 1px solid #60a5fa !important;
}
tr.imnehnet-pair-student-victim > td {
    border-bottom: 1px solid #60a5fa !important;
}
tr.imnehnet-pair-admin-attacker > td:first-child,
tr.imnehnet-pair-admin-victim > td:first-child {
    border-left: 2px solid #14b8a6 !important;
}
tr.imnehnet-pair-admin-attacker > td:last-child,
tr.imnehnet-pair-admin-victim > td:last-child {
    border-right: 2px solid #14b8a6 !important;
}
tr.imnehnet-pair-student-attacker > td:first-child,
tr.imnehnet-pair-student-victim > td:first-child {
    border-left: 2px solid #60a5fa !important;
}
tr.imnehnet-pair-student-attacker > td:last-child,
tr.imnehnet-pair-student-victim > td:last-child {
    border-right: 2px solid #60a5fa !important;
}
tr.imnehnet-pair-admin-attacker > td:first-child,
tr.imnehnet-pair-student-attacker > td:first-child {
    border-top-left-radius: 12px;
}
tr.imnehnet-pair-admin-attacker > td:last-child,
tr.imnehnet-pair-student-attacker > td:last-child {
    border-top-right-radius: 12px;
}
tr.imnehnet-pair-admin-victim > td:first-child,
tr.imnehnet-pair-student-victim > td:first-child {
    border-bottom-left-radius: 12px;
}
tr.imnehnet-pair-admin-victim > td:last-child,
tr.imnehnet-pair-student-victim > td:last-child {
    border-bottom-right-radius: 12px;
}
CSS;

                        return new \Illuminate\Support\HtmlString(
                            '<style>'.$css.'</style>'.e((string) $state)
                        );
                    })


                    // IMNEHNET_PAIR_VICTIM_BOLD_V2
                    ->weight(
                        fn (\App\Models\LabEnvironment $record) =>
                            str_starts_with(
                                (string) $record->container_name,
                                'victim-'
                            )
                                ? 'bold'
                                : 'medium'
                    )
                    ->label('Container')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->description(fn (LabEnvironment $record): ?string => $record->isProtected()
                        ? 'Protected reference environment'
                        : null),

                // imnehnet-environment-owner-column-v1
                // Owner is resolved using the existing LabEnvironment relation.
                TextColumn::make('user_identity')
                    ->label('User')
                    ->state(
                        fn (LabEnvironment $record): string =>
                            $record->user_id === null
                                ? 'Unassigned'
                                : (
                                    \App\Models\User::query()
                                        ->find($record->user_id)?->name
                                    ?? 'User not found'
                                )
                    )
                    ->formatStateUsing(
                        function (
                            string $state,
                            LabEnvironment $record
                        ): \Illuminate\Support\HtmlString {
                            $owner = $record->user_id === null
                                ? null
                                : \App\Models\User::query()
                                    ->find($record->user_id);

                            $badge = $owner?->isAdmin()
                                ? '<span style="
                                    display:inline-block;
                                    margin-left:8px;
                                    padding:2px 7px;
                                    border-radius:5px;
                                    background:rgba(239,68,68,.14);
                                    border:1px solid rgba(239,68,68,.4);
                                    color:#f87171;
                                    font-size:10px;
                                    font-weight:700;
                                  ">ADMIN</span>'
                                : '';

                            return new \Illuminate\Support\HtmlString(
                                '<span>'.e($state).'</span>'.$badge
                            );
                        }
                    )
                    ->html()
                    ->description(
                        fn (LabEnvironment $record): ?string =>
                            $record->user_id === null
                                ? null
                                : 'User ID: '.$record->user_id
                    ),

                TextColumn::make('internal_ip')
                    ->label('IP address')
                    // Never invented. An address that has not been observed is
                    // shown as such rather than left blank, which reads as an
                    // oversight rather than an honest gap.
                    ->placeholder('Not assigned')
                    ->fontFamily('mono')
                    ->color(fn (mixed $state): ?string => filled($state) ? null : 'gray'),

                TextColumn::make('image')
                    ->label('Image')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('cpu_limit')
                    ->label('CPU')
                    ->formatStateUsing(fn (mixed $state): string => $state === null
                        ? '—'
                        : sprintf('%s core%s', $state, (int) $state === 1 ? '' : 's'))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('memory_limit_mb')
                    ->label('Memory')
                    ->formatStateUsing(fn (mixed $state): string => $state === null
                        ? '—'
                        : sprintf('%d MiB', (int) $state))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Provisioning')
                    ->badge()
                    ->formatStateUsing(fn (EnvironmentStatus $state): string => $state->label())
                    ->color(fn (EnvironmentStatus $state): string => $state->color())
                    ->icon(fn (EnvironmentStatus $state): ?Htmlable => $state->icon()),

                TextColumn::make('runtime_status')
                    ->label('Runtime')
                    ->badge()
                    ->formatStateUsing(fn (RuntimeStatus $state): string => $state->label())
                    ->color(fn (RuntimeStatus $state): string => $state->color())
                    ->icon(fn (RuntimeStatus $state): ?Htmlable => $state->icon())
                    ->extraAttributes(fn (LabEnvironment $record): array => $record->hasStateMismatch()
                        ? ['title' => 'State mismatch: the record and the container disagree']
                        : []),

                TextColumn::make('tofu_workspace')
                    ->label('Workspace')
                    ->fontFamily('mono')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Not assigned'),

                TextColumn::make('updated_at')
                    ->label('Last update')
                    ->since()
                    ->placeholder('Never')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // IMNEHNET_PAIR_SORT_V3
            // Group by normalized pair identity, attacker before victim.
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw(
                    "REPLACE(REPLACE(container_name, 'attacker-', ''), 'victim-', '') ASC"
                )
                ->orderByRaw(
                    "CASE
                        WHEN container_name LIKE 'attacker-%' THEN 0
                        WHEN container_name LIKE 'victim-%' THEN 1
                        ELSE 2
                    END ASC"
                )
                ->orderBy('container_name')
            )
            ->filters([
                SelectFilter::make('status')
                    ->label('Provisioning status')
                    ->options(collect(EnvironmentStatus::cases())
                        ->mapWithKeys(fn (EnvironmentStatus $case): array => [$case->value => $case->label()])
                        ->all()),
                SelectFilter::make('runtime_status')
                    ->label('Runtime status')
                    ->options(collect(RuntimeStatus::cases())
                        ->mapWithKeys(fn (RuntimeStatus $case): array => [$case->value => $case->label()])
                        ->all()),
                Filter::make('mismatch')
                    ->label('State mismatch only')
                    ->query(fn (Builder $query): Builder => $query
                        ->where(function (Builder $query): void {
                            $query
                                ->where(fn (Builder $q): Builder => $q
                                    ->where('status', EnvironmentStatus::Ready->value)
                                    ->where('runtime_status', '!=', RuntimeStatus::Running->value))
                                ->orWhere(fn (Builder $q): Builder => $q
                                    ->where('status', EnvironmentStatus::Stopped->value)
                                    ->where('runtime_status', RuntimeStatus::Running->value));
                        })),
            ])

            ->recordClasses(function (\App\Models\LabEnvironment $record): ?string {
                $id = (int) $record->getKey();

                $admin = \App\Models\AdminEnvironmentPair::forEnvironment($id);

                if ($admin !== null) {
                    if ((int) $admin->attacker_environment_id === $id) {
                        return 'imnehnet-pair-admin-attacker';
                    }

                    if ((int) $admin->victim_environment_id === $id) {
                        return 'imnehnet-pair-admin-victim';
                    }

                    return null;
                }

                $session = \App\Models\LabSession::query()
                    ->where(function ($query) use ($id): void {
                        $query
                            ->where('attacker_environment_id', $id)
                            ->orWhere('victim_environment_id', $id);
                    })
                    ->where(
                        'status',
                        '!=',
                        \App\Enums\LabSessionStatus::Deleted->value
                    )
                    ->latest('id')
                    ->first();

                if ($session === null) {
                    return null;
                }

                if ((int) $session->attacker_environment_id === $id) {
                    return 'imnehnet-pair-student-attacker';
                }

                if ((int) $session->victim_environment_id === $id) {
                    return 'imnehnet-pair-student-victim';
                }

                return null;
            })
            ->recordActions([
                // IMNEHNET_REMOVE_VIEW_V2
                // Details remain accessible via table row navigation.
                // Admin-owned pairs are not LabSession / Guided CTF records.
                // IMNEHNET_ADMIN_UI_V3
                \Filament\Actions\Action::make('adminStandalonePairTerminal')
                    ->label('Pair Terminal')
                    ->iconButton()
                    ->icon(Lucide::icon('terminal'))
                    ->color('primary')
                    ->tooltip('Open Attacker and Victim terminal')
                    ->visible(function (LabEnvironment $record): bool {
                        $u = auth()->user();
                        if (!$u?->isAdmin() || !$u->isActive()) return false;
                        $p = AdminEnvironmentPair::forEnvironment((int) $record->getKey());
                        return $p !== null
                            && (int) $p->attacker_environment_id === (int) $record->getKey()
                            && $p->status === 'READY'
                            && $record->runtime_status === RuntimeStatus::Running
                            && LabEnvironment::query()
                                ->whereKey($p->victim_environment_id)
                                ->where('runtime_status', RuntimeStatus::Running->value)
                                ->exists();
                    })
                    ->url(fn (LabEnvironment $record): string =>
                        route('admin.standalone-pair.show', [
                            'environment' => $record->getKey(),
                        ])),

                // IMNEHNET_DETAIL_ICON_FINAL


                // IMNEHNET_HYBRID_RUNTIME_V1
                // IMNEHNET_ADMIN_PAIRWIDE_V2
                // All runtime operations originate from Attacker.
                // Both members are operated by AdminPairRuntimeManager.

                Action::make('stopAdminPairV2')
                    ->label('Stop Pair')
                    ->iconButton()
                    ->icon(Lucide::icon('square'))
                    ->color('gray')
                    ->tooltip('Stop both Attacker and Victim')
                    ->requiresConfirmation()
                    ->modalHeading('Stop entire Admin Pair?')
                    ->modalDescription(
                        'Both Attacker and Victim containers will stop.'
                    )
                    ->visible(function (LabEnvironment $record): bool {
                        $user = auth()->user();

                        if (!$user?->isAdmin() || !$user->isActive()) {
                            return false;
                        }

                        $pair = AdminEnvironmentPair::forEnvironment(
                            (int) $record->getKey()
                        );

                        if (
                            !$pair
                            || ! $user->canOperateInfrastructure()
                            || (int) $pair->attacker_environment_id
                                !== (int) $record->getKey()
                            || $pair->status !== 'READY'
                        ) {
                            return false;
                        }

                        $members = LabEnvironment::query()
                            ->whereIn('id', [
                                $pair->attacker_environment_id,
                                $pair->victim_environment_id,
                            ])
                            ->get();

                        if ($members->count() !== 2) {
                            return false;
                        }

                        foreach ($members as $member) {
                            if (
                                !in_array($member->status, [
                                    EnvironmentStatus::Ready,
                                    EnvironmentStatus::Stopped,
                                ], true)
                                || !in_array($member->runtime_status, [
                                    RuntimeStatus::Running,
                                    RuntimeStatus::Stopped,
                                ], true)
                            ) {
                                return false;
                            }
                        }

                        return $members->contains(
                            fn (LabEnvironment $member): bool =>
                                $member->runtime_status === RuntimeStatus::Running
                        );
                    })
                    ->action(function (LabEnvironment $record): void {
                        try {
                            app(\App\Services\AdminPairRuntimeManager::class)
                                ->request(auth()->user(), $record, 'stop');

                            Notification::make()
                                ->title('Stop Pair queued')
                                ->body('Stopping Attacker and Victim.')
                                ->success()
                                ->send();
                        } catch (\Throwable $error) {
                            report($error);

                            Notification::make()
                                ->title('Stop Pair rejected')
                                ->body(substr($error->getMessage(), 0, 180))
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('startAdminPairV2')
                    ->label('Start Pair')
                    ->iconButton()
                    ->icon(Lucide::icon('play'))
                    ->color('success')
                    ->tooltip('Start both Attacker and Victim')
                    ->requiresConfirmation()
                    ->modalHeading('Start entire Admin Pair?')
                    ->modalDescription(
                        'Both Attacker and Victim containers will start.'
                    )
                    ->visible(function (LabEnvironment $record): bool {
                        $user = auth()->user();

                        if (!$user?->isAdmin() || !$user->isActive()) {
                            return false;
                        }

                        $pair = AdminEnvironmentPair::forEnvironment(
                            (int) $record->getKey()
                        );

                        if (
                            !$pair
                            || ! $user->canOperateInfrastructure()
                            || (int) $pair->attacker_environment_id
                                !== (int) $record->getKey()
                            || $pair->status !== 'READY'
                        ) {
                            return false;
                        }

                        $members = LabEnvironment::query()
                            ->whereIn('id', [
                                $pair->attacker_environment_id,
                                $pair->victim_environment_id,
                            ])
                            ->get();

                        if ($members->count() !== 2) {
                            return false;
                        }

                        foreach ($members as $member) {
                            if (
                                !in_array($member->status, [
                                    EnvironmentStatus::Ready,
                                    EnvironmentStatus::Stopped,
                                ], true)
                                || !in_array($member->runtime_status, [
                                    RuntimeStatus::Running,
                                    RuntimeStatus::Stopped,
                                ], true)
                            ) {
                                return false;
                            }
                        }

                        return $members->contains(
                            fn (LabEnvironment $member): bool =>
                                $member->runtime_status === RuntimeStatus::Stopped
                        );
                    })
                    ->action(function (LabEnvironment $record): void {
                        try {
                            app(\App\Services\AdminPairRuntimeManager::class)
                                ->request(auth()->user(), $record, 'start');

                            Notification::make()
                                ->title('Start Pair queued')
                                ->body('Starting Attacker and Victim.')
                                ->success()
                                ->send();
                        } catch (\Throwable $error) {
                            report($error);

                            Notification::make()
                                ->title('Start Pair rejected')
                                ->body(substr($error->getMessage(), 0, 180))
                                ->danger()
                                ->send();
                        }
                    }),

                // IMNEHNET_PAIR_UI_STAGE_C_V2
                \Filament\Actions\Action::make('adminEnvironmentPair')
                    ->iconButton()
                    ->icon(Lucide::icon('terminal'))
                    ->tooltip('Pair Terminal')
                    ->label('Pair Terminal')
                    ->color('primary')
                    ->visible(
                        fn (\App\Models\LabEnvironment $record): bool =>
                            auth()->user()?->canOperateInfrastructure() === true
                            && \App\Models\LabSession::query()
                                ->where(
                                    'attacker_environment_id',
                                    $record->getKey()
                                )->exists()
                    )
                    ->url(
                        fn (\App\Models\LabEnvironment $record): string =>
                            route('admin.environment-pair.show', [
                                'environment' => $record->getKey(),
                            ])
                    ),


                // Spread rather than nested: recordActions() iterates the array
                // it is given and calls table() on each element, so a nested
                // list would be treated as an action.
                // IMNEHNET_ADMIN_RESET_UI_V1
                // Pair-wide reset. Never expose individual reset on Admin members.
                Action::make('resetAdminPair')
                    ->label('Reset Pair')
                    ->iconButton()
                    ->icon(Lucide::icon('rotate-ccw'))
                    ->color('warning')
                    ->tooltip('Reset both Admin Pair containers')
                    ->requiresConfirmation()
                    ->modalHeading('Reset entire Admin Pair?')
                    ->modalDescription(
                        'Both Attacker and Victim will be recreated from '
                        .'their original images. All changes inside both '
                        .'containers will be permanently lost. '
                        .'Close the pair terminals before continuing.'
                    )
                    ->modalSubmitActionLabel('Reset both containers')
                    ->visible(function (LabEnvironment $record): bool {
                        $user = auth()->user();

                        if (
                            !$user?->isAdmin()
                            || !$user->isActive()
                            || $record->isProtected()
                        ) {
                            return false;
                        }

                        $pair = AdminEnvironmentPair::forEnvironment(
                            (int) $record->getKey()
                        );

                        if (
                            !$pair
                            || $pair->status !== 'READY'
                            || (int) $pair->attacker_environment_id
                                !== (int) $record->getKey()
                            || $record->status !== EnvironmentStatus::Ready
                            || $record->runtime_status
                                !== RuntimeStatus::Running
                        ) {
                            return false;
                        }

                        return LabEnvironment::query()
                            ->whereKey($pair->victim_environment_id)
                            ->where(
                                'status',
                                EnvironmentStatus::Ready->value
                            )
                            ->where(
                                'runtime_status',
                                RuntimeStatus::Running->value
                            )
                            ->exists();
                    })
                    ->action(function (LabEnvironment $record): void {
                        try {
                            app(\App\Services\AdminPairResetManager::class)
                                ->request(auth()->user(), $record);

                            Notification::make()
                                ->title('Admin Pair reset queued')
                                ->body(
                                    'Attacker and Victim will be '
                                    .'recreated together.'
                                )
                                ->warning()
                                ->send();
                        } catch (\Throwable $error) {
                            report($error);

                            Notification::make()
                                ->title('Reset Pair rejected')
                                ->body(
                                    substr($error->getMessage(), 0, 180)
                                )
                                ->danger()
                                ->send();
                        }
                    }),

                ...EnvironmentActions::all(),
                Action::make('deleteAdminPair')
                    ->label('Delete Pair')
                    ->iconButton()
                    ->icon(Lucide::icon('trash'))
                    ->color('danger')
                    ->tooltip('Delete both Admin Pair containers')
                    ->requiresConfirmation()
                    ->modalHeading('Delete entire Admin Pair?')
                    ->modalDescription('Attacker, Victim, their files, and OpenTofu workspaces will be permanently deleted. Also available for ERROR pairs. This cannot be undone.')
                    ->modalSubmitActionLabel('Delete both containers')
                    ->visible(function (LabEnvironment $record): bool {
                        $u = auth()->user();
                        if (!$u?->isAdmin() || !$u->isActive()) return false;
                        $p = AdminEnvironmentPair::forEnvironment((int) $record->getKey());
                        return $p !== null
                            && (int) $p->attacker_environment_id === (int) $record->getKey()
                            && in_array($p->status, ['READY', 'ERROR'], true);
                    })
                    ->action(function (LabEnvironment $record): void {
                        try {
                            app(\App\Services\AdminEnvironmentPairDeleter::class)
                                ->request(auth()->user(), $record);
                            Notification::make()
                                ->title('Admin Pair deletion queued')
                                ->warning()->send();
                        } catch (\Throwable $e) {
                            report($e);
                            Notification::make()
                                ->title('Delete Pair rejected')
                                ->body(substr($e->getMessage(), 0, 180))
                                ->danger()->send();
                        }
                    }),
            ])
            // poll() takes a closure rather than a condition, so the interval
            // is returned only while something is in flight and null once every
            // environment has settled. A permanent timer would keep hitting the
            // database and the compute plane for a list that is not changing.
            ->poll(fn (): ?string => self::shouldPoll() ? '4s' : null)
            ->paginated([10, 25, 50]);
    }

    /**
     * True while at least one environment is mid-operation.
     */
    public static function shouldPoll(): bool
    {
        return LabEnvironment::query()
            ->whereIn('status', [
                EnvironmentStatus::Provisioning->value,
                EnvironmentStatus::Configuring->value,
                EnvironmentStatus::Resetting->value,
                EnvironmentStatus::Deleting->value,
            ])
            ->exists();
    }
}
