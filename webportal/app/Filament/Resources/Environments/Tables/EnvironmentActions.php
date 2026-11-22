<?php

namespace App\Filament\Resources\Environments\Tables;

use App\Contracts\LifecycleDriver;
use App\Models\LabEnvironment;
use App\Models\AdminEnvironmentPair;
use App\Services\EnvironmentLifecycleService;
use App\Support\Lucide;
use DomainException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Throwable;

/**
 * The lifecycle actions offered on each environment row.
 *
 * The set is declared once and each action decides for itself whether it
 * applies to a given row, because Filament evaluates visibility per record
 * rather than per table.
 *
 * What is allowed is decided by the lifecycle service, not here, so the table
 * and the service cannot disagree about it. Two consequences worth stating: an
 * environment whose operation is still in flight offers nothing, because a
 * second operation would race the first through a shared OpenTofu workspace;
 * and the protected reference environment offers nothing, because it exists to
 * be observed.
 *
 * None of these actions reports success. Infrastructure work takes far longer
 * than a request, so each one records the intention and hands it to the queue.
 * The row shows work in progress until the job settles it.
 */
final class EnvironmentActions
{
    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [
            self::start(),
            self::stop(),
            self::reset(),
            self::refresh(),

            // IMNEHNET_ADMIN_DELETE_PAIR_V8
            self::delete(),
        ];
    }


    // IMNEHNET_STUDENT_PAIR_RUNTIME_V2
    private static function activeStudentPair(
        LabEnvironment $record
    ): ?\App\Models\LabSession {
        $sessions = \App\Models\LabSession::query()
            ->where(function ($query) use ($record) {
                $query
                    ->where(
                        'attacker_environment_id',
                        $record->getKey()
                    )
                    ->orWhere(
                        'victim_environment_id',
                        $record->getKey()
                    );
            })
            ->where('status', '!=', 'DELETED')
            ->limit(2)
            ->get();

        if ($sessions->count() > 1) {
            throw new DomainException(
                'Ambiguous Student Pair membership.'
            );
        }

        return $sessions->first();
    }

    private static function pairRuntimeVisible(
        LabEnvironment $record,
        string $operation
    ): bool {
        try {
            $session = self::activeStudentPair($record);

            if (!$session) {
                return self::allows($record, $operation);
            }

            $actor = auth()->user();

            if (
                !$actor?->isAdmin()
                || !$actor->isActive()
                || (int) $session->attacker_environment_id
                    !== (int) $record->getKey()
            ) {
                return false;
            }

            $status = $session->status instanceof \BackedEnum
                ? $session->status->value
                : (string) $session->status;

            if (!in_array(
                $status,
                ['READY', 'RUNNING', 'STOPPED'],
                true
            )) {
                return false;
            }

            [$attacker, $victim] = app(
                \App\Services\GuidedCtf\StudentPairRuntimeManager::class
            )->members($session);

            $desired = $operation === 'start'
                ? \App\Enums\RuntimeStatus::Running
                : \App\Enums\RuntimeStatus::Stopped;

            foreach ([$attacker, $victim] as $env) {
                if (
                    !in_array($env->status, [
                        \App\Enums\EnvironmentStatus::Ready,
                        \App\Enums\EnvironmentStatus::Stopped,
                    ], true)
                    || !in_array($env->runtime_status, [
                        \App\Enums\RuntimeStatus::Running,
                        \App\Enums\RuntimeStatus::Stopped,
                    ], true)
                ) {
                    return false;
                }
            }

            return $attacker->runtime_status !== $desired
                || $victim->runtime_status !== $desired;
        } catch (\Throwable $error) {
            return false;
        }
    }

    private static function pairRuntimeAction(
        LabEnvironment $record,
        string $operation
    ): void {
        try {
            $session = self::activeStudentPair($record);

            if (!$session) {
                self::dispatch($operation, $record);
                return;
            }

            app(
                \App\Services\GuidedCtf\StudentPairRuntimeManager::class
            )->request(
                auth()->user(),
                $record,
                $operation
            );

            Notification::make()
                ->title('Student Pair operation queued')
                ->body(
                    ucfirst($operation)
                    .' requested for Attacker and Victim.'
                )
                ->success()
                ->send();
        } catch (\Throwable $error) {
            report($error);

            Notification::make()
                ->title('Student Pair operation rejected')
                ->body(substr($error->getMessage(), 0, 180))
                ->danger()
                ->send();
        }
    }

    // IMNEHNET_STUDENT_RESET_PAIR_V3
    private static function studentResetVisible(
        LabEnvironment $record
    ): bool {
        try {
            $session = self::activeStudentPair($record);

            if (!$session) {
                return self::allows($record, 'reset');
            }

            $actor = auth()->user();

            if (
                !$actor?->isAdmin()
                || !$actor->isActive()
                || (int) $session->attacker_environment_id
                    !== (int) $record->getKey()
            ) {
                return false;
            }

            $status = $session->status instanceof \BackedEnum
                ? $session->status->value
                : (string) $session->status;

            if (!in_array(
                $status,
                ['READY', 'RUNNING', 'STOPPED', 'COMPLETED'],
                true
            )) {
                return false;
            }

            [$attacker, $victim] = app(
                \App\Services\GuidedCtf\StudentPairRuntimeManager::class
            )->members($session);

            foreach ([$attacker, $victim] as $env) {
                if (
                    !in_array($env->status, [
                        \App\Enums\EnvironmentStatus::Ready,
                        \App\Enums\EnvironmentStatus::Stopped,
                    ], true)
                ) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $error) {
            return false;
        }
    }

    private static function safeReset(
        LabEnvironment $record
    ): void {
        try {
            $session = self::activeStudentPair($record);

            if (!$session) {
                self::dispatch('reset', $record);
                return;
            }

            $actor = auth()->user();

            if (!$actor?->isAdmin() || !$actor->isActive()) {
                throw new DomainException(
                    'Only an active administrator may reset Student Pair.'
                );
            }

            if (
                (int) $session->attacker_environment_id
                !== (int) $record->getKey()
            ) {
                throw new DomainException(
                    'Reset Pair must originate from Attacker.'
                );
            }

            $manager = app(
                \App\Services\GuidedCtf\StudentPairRuntimeManager::class
            );

            $previousStatus = null;
            $sessionId = (int) $session->getKey();

            \Illuminate\Support\Facades\DB::connection(
                $session->getConnectionName()
            )->transaction(function () use (
                $sessionId,
                $record,
                $manager,
                &$previousStatus
            ): void {
                $fresh = \App\Models\LabSession::query()
                    ->lockForUpdate()
                    ->findOrFail($sessionId);

                $status = $fresh->status instanceof \BackedEnum
                    ? $fresh->status->value
                    : (string) $fresh->status;

                if (!in_array(
                    $status,
                    ['READY', 'RUNNING', 'STOPPED', 'COMPLETED'],
                    true
                )) {
                    throw new DomainException(
                        'LabSession is not available for Reset Pair.'
                    );
                }

                if (
                    (int) $fresh->attacker_environment_id
                    !== (int) $record->getKey()
                ) {
                    throw new DomainException(
                        'Student Pair identity changed.'
                    );
                }

                [$attacker, $victim] = $manager->members($fresh);

                $locked = LabEnvironment::query()
                    ->whereIn('id', [
                        $attacker->id,
                        $victim->id,
                    ])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($locked->count() !== 2) {
                    throw new DomainException(
                        'Student Pair inventory incomplete.'
                    );
                }

                foreach ([
                    ['attacker', $attacker],
                    ['victim', $victim],
                ] as [$role, $original]) {
                    $env = $locked->get($original->id);

                    $manager->validateMember(
                        $fresh,
                        $env,
                        $role
                    );

                    if (!in_array($env->status, [
                        \App\Enums\EnvironmentStatus::Ready,
                        \App\Enums\EnvironmentStatus::Stopped,
                    ], true)) {
                        throw new DomainException(
                            'An environment operation is in progress.'
                        );
                    }
                }

                $previousStatus = $fresh->status;

                $fresh->forceFill([
                    'status' =>
                        \App\Enums\LabSessionStatus::Resetting,
                    'last_error' => null,
                ])->save();
            });

            try {
                \Illuminate\Support\Facades\Queue::push(
                    new \App\Jobs\ResetLabSession($sessionId)
                );
            } catch (\Throwable $queueError) {
                // Restore only if no worker has advanced the state.
                \Illuminate\Support\Facades\DB::connection(
                    $session->getConnectionName()
                )->transaction(function () use (
                    $sessionId,
                    $previousStatus
                ): void {
                    $fresh = \App\Models\LabSession::query()
                        ->lockForUpdate()
                        ->find($sessionId);

                    if (
                        $fresh
                        && $fresh->status
                            === \App\Enums\LabSessionStatus::Resetting
                    ) {
                        $fresh->forceFill([
                            'status' => $previousStatus,
                            'last_error' =>
                                'Reset queue dispatch failed.',
                        ])->save();
                    }
                });

                throw $queueError;
            }

            Notification::make()
                ->title('Student Reset Pair queued')
                ->body(
                    'Reset Attacker dan Victim telah dijadwalkan '
                    .'untuk LabSession #'.$sessionId.'.'
                )
                ->warning()
                ->send();

        } catch (\Throwable $error) {
            report($error);

            Notification::make()
                ->title('Student Reset Pair rejected')
                ->body(substr($error->getMessage(), 0, 180))
                ->danger()
                ->send();
        }
    }

    private static function start(): Action
    {
        return Action::make('start')
            ->iconButton()
            ->label('Start')
            ->icon(Lucide::icon('play'))
            ->color('success')
            ->tooltip('Converge the container onto running')
            ->visible(fn (LabEnvironment $record): bool => self::pairRuntimeVisible($record, 'start'))
            ->action(fn (LabEnvironment $record) => self::pairRuntimeAction($record, 'start'));
    }

    private static function stop(): Action
    {
        return Action::make('stop')
            ->iconButton()
            ->label('Stop')
            ->icon(Lucide::icon('square'))
            ->color('gray')
            ->tooltip('Converge the container onto stopped')
            ->visible(fn (LabEnvironment $record): bool => self::pairRuntimeVisible($record, 'stop'))
            ->action(fn (LabEnvironment $record) => self::pairRuntimeAction($record, 'stop'));
    }

    private static function reset(): Action
    {
        return Action::make('reset')
            ->iconButton()
            ->label('Reset')
            ->icon(Lucide::icon('rotate-ccw'))
            ->color('warning')
            // Reset destroys the container and builds it again, so the address it
            // returns with is not the one an operator may have written down.
            // That has to be said before the button is pressed.
            ->requiresConfirmation()
            ->modalHeading('Reset environment / Student Pair?')
            ->modalDescription('For Student Pairs, both Attacker and Victim will be rebuilt through the existing LabSession reset workflow. Lab data may be lost. For independent environments, only the selected container is reset.')
            ->modalSubmitActionLabel('Reset environment')
            ->visible(fn (LabEnvironment $record): bool => self::studentResetVisible($record))
            ->action(fn (LabEnvironment $record) => self::safeReset($record));
    }

    /**
     * Read-only. Reads what the infrastructure currently believes and updates
     * only the observed runtime, never the desired status, so a disagreement is
     * shown rather than quietly resolved in favour of one side.
     */
    private static function refresh(): Action
    {
        return Action::make('refresh')
            ->iconButton()
            ->label('Refresh status')
            ->icon(Lucide::icon('refresh-cw'))
            ->color('gray')
            ->tooltip('Read the actual state without changing anything')
            ->visible(function (LabEnvironment $record): bool {
                // IMNEHNET_PAIR_REFRESH_UI_V2
                if (! self::allows($record, 'refresh')) {
                    return false;
                }

                $pair = AdminEnvironmentPair::forEnvironment(
                    (int) $record->getKey()
                );

                if ($pair !== null) {
                    return (int) $pair->attacker_environment_id
                        === (int) $record->getKey();
                }

                $sessions = \App\Models\LabSession::query()
                    ->where(function ($query) use ($record) {
                        $query->where(
                            'attacker_environment_id',
                            $record->getKey()
                        )->orWhere(
                            'victim_environment_id',
                            $record->getKey()
                        );
                    })
                    ->where('status', '!=', 'DELETED')
                    ->get();

                if ($sessions->isEmpty()) {
                    return true;
                }

                return $sessions->count() === 1
                    && (int) $sessions->first()->attacker_environment_id
                        === (int) $record->getKey()
                    && in_array(
                        $sessions->first()->status instanceof \BackedEnum
                            ? $sessions->first()->status->value
                            : (string) $sessions->first()->status,
                        ['READY', 'RUNNING', 'STOPPED', 'COMPLETED'],
                        true
                    );
            })
            ->action(function (LabEnvironment $record): void {
                // IMNEHNET_PAIR_REFRESH_CALLBACK_V2
                $isAdminPair = AdminEnvironmentPair::forEnvironment(
                    (int) $record->getKey()
                ) !== null;

                $isStudentPair = \App\Models\LabSession::query()
                    ->where(function ($query) use ($record) {
                        $query->where(
                            'attacker_environment_id',
                            $record->getKey()
                        )->orWhere(
                            'victim_environment_id',
                            $record->getKey()
                        );
                    })
                    ->where('status', '!=', 'DELETED')
                    ->exists();

                if ($isAdminPair || $isStudentPair) {
                    try {
                        $states = app(
                            \App\Services\PairIncusStatusRefresher::class
                        )->refresh(auth()->user(), $record);

                        Notification::make()
                            ->title('Pair status refreshed')
                            ->body(
                                'Attacker: '.$states['Attacker']->value
                                .' | Victim: '.$states['Victim']->value
                            )
                            ->success()
                            ->send();
                    } catch (\Throwable $error) {
                        report($error);

                        Notification::make()
                            ->title('Could not refresh Pair')
                            ->body(substr($error->getMessage(), 0, 180))
                            ->danger()
                            ->send();
                    }

                    return;
                }

                        // IMNEHNET_ADMIN_REFRESH_INCUS_V1
                        // Admin Pair: Incus-only runtime refresh.
                        // Student: continue through original callback.
                        if (
                            \App\Models\AdminEnvironmentPair::forEnvironment(
                                (int) $record->getKey()
                            ) !== null
                        ) {
                            try {
                                $runtime = app(
                                    \App\Services\AdminPairIncusStatusRefresher::class
                                )->refresh(auth()->user(), $record);

                                \Filament\Notifications\Notification::make()
                                    ->title('Container status refreshed')
                                    ->body('Actual Incus status: '.$runtime->value)
                                    ->success()
                                    ->send();
                            } catch (\Throwable $error) {
                                report($error);

                                \Filament\Notifications\Notification::make()
                                    ->title('Could not refresh container')
                                    ->body(substr($error->getMessage(), 0, 180))
                                    ->danger()
                                    ->send();
                            }

                            return;
                        }


                // IMNEHNET_STUDENT_REFRESH_INCUS_V1
                // Student Refresh: observe only this Student container.
                try {
                    $runtime = app(
                        \App\Services\StudentIncusStatusRefresher::class
                    )->refresh(auth()->user(), $record);

                    \Filament\Notifications\Notification::make()
                        ->title('Student container status refreshed')
                        ->body('Actual Incus status: '.$runtime->value)
                        ->success()
                        ->send();
                } catch (\Throwable $error) {
                    report($error);

                    \Filament\Notifications\Notification::make()
                        ->title('Could not refresh Student container')
                        ->body(substr($error->getMessage(), 0, 180))
                        ->danger()
                        ->send();
                }

                return;

                try {
                    $observed = app(LifecycleDriver::class)->observe($record);
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title('Could not read the actual state')
                        ->body(substr($exception->getMessage(), 0, 160))
                        ->danger()
                        ->send();

                    return;
                }

                app(EnvironmentLifecycleService::class)->reconcile($record, $observed);

                $fresh = $record->fresh();
                $mismatch = $fresh->hasStateMismatch();

                Notification::make()
                    ->title($mismatch ? 'State mismatch' : 'Status refreshed')
                    ->body($mismatch
                        ? sprintf(
                            'The record says %s while the container reports %s.',
                            $fresh->status->value,
                            $fresh->runtime_status->value
                        )
                        : sprintf('The container reports %s.', $fresh->runtime_status->value))
                    ->warning($mismatch)
                    ->success(! $mismatch)
                    ->persistent()
                    ->send();
            });
    }


    /**
     * Delete the whole managed attacker/victim lab.
     * The queue worker performs infrastructure teardown.
     */
    // IMNEHNET_UNIFIED_PAIR_DELETE_V10
    // Exactly one Delete action for independent and managed environments.
    private static function delete(): Action
    {
        $linkedLab = static function (LabEnvironment $record): ?\App\Models\LabSession {
            return \App\Models\LabSession::query()
                ->where(function ($query) use ($record): void {
                    $query
                        ->where('attacker_environment_id', $record->getKey())
                        ->orWhere('victim_environment_id', $record->getKey());
                })
                ->latest('id')
                ->first();
        };

        $looksPaired = static function (LabEnvironment $record): bool {
            return preg_match(
                '/^(attacker|victim)-user-[0-9]+$/D',
                (string) $record->container_name
            ) === 1;
        };

        return Action::make('delete')
            // IMNEHNET_STUDENT_VICTIM_DELETE_HIDE_V1
            // Delete Pair is controlled exclusively from Attacker.
            ->hidden(function (LabEnvironment $record): bool {
                return \App\Models\LabSession::query()
                    ->where(
                        'victim_environment_id',
                        $record->getKey()
                    )
                    ->exists();
            })

            ->iconButton()
            ->label('Delete')
            ->icon(Lucide::icon('trash'))
            ->color('danger')
            ->requiresConfirmation()

            ->modalHeading(
                fn (LabEnvironment $record): string =>
                    $linkedLab($record) !== null || $looksPaired($record)
                        ? 'Delete paired lab?'
                        : 'Delete environment?'
            )

            ->modalDescription(
                fn (LabEnvironment $record): string =>
                    $linkedLab($record) !== null
                        ? 'Attacker dan Victim akan dihapus sebagai satu pasangan. '
                            .'Histori lab dipertahankan. Kuota baru dilepas '
                            .'setelah kedua container berhasil dihapus.'
                        : (
                            $looksPaired($record)
                                ? 'Container terlihat sebagai bagian dari paired lab, '
                                    .'tetapi relasinya belum ditemukan. '
                                    .'Sistem akan menolak penghapusan individual '
                                    .'untuk mencegah kerusakan lab mahasiswa.'
                                : 'Container independen akan dihapus '
                                    .'melalui lifecycle infrastruktur.'
                        )
            )

            ->modalSubmitActionLabel('Delete')

            ->visible(function (LabEnvironment $record) use (
                $linkedLab,
                $looksPaired
            ): bool {
                $admin = auth()->user();

                if (
                    ! $admin
                    || ! $admin->isActive()
                    || ! $admin->canOperateInfrastructure()
                    || $record->isProtected()
                ) {
                    return false;
                }

                // Admin pairs must only be deleted by the coordinated pair action.
                if (AdminEnvironmentPair::forEnvironment((int) $record->getKey()) !== null) {
                    return false;
                }

                if ($linkedLab($record) !== null || $looksPaired($record)) {
                    return true;
                }

                return self::allows($record, 'delete');
            })

            ->action(function (LabEnvironment $record) use (
                $linkedLab,
                $looksPaired
            ): void {
                try {
                    $admin = auth()->user();

                    if (
                        ! $admin
                        || ! $admin->isActive()
                        || ! $admin->canOperateInfrastructure()
                        || $record->isProtected()
                    ) {
                        throw new DomainException(
                            'Tidak memiliki izin menghapus environment.'
                        );
                    }

                    if (AdminEnvironmentPair::forEnvironment((int) $record->getKey()) !== null) {
                        throw new DomainException('Use Delete Admin Pair to remove both containers.');
                    }

                    // Resolve by the actual LabSession relationship.
                    // Never infer a deletion target from a name suffix.
                    $labs = \App\Models\LabSession::query()
                        ->where(function ($query) use ($record): void {
                            $query
                                ->where('attacker_environment_id', $record->getKey())
                                ->orWhere('victim_environment_id', $record->getKey());
                        })
                        ->latest('id')
                        ->limit(2)
                        ->get();

                    if ($labs->count() > 1) {
                        throw new DomainException(
                            'Environment memiliki relasi lab ambigu. '
                            .'Penghapusan diblokir untuk pemeriksaan admin.'
                        );
                    }

                    $lab = $labs->first();

                    if ($lab !== null) {
                        if ($lab->status === \App\Enums\LabSessionStatus::Deleted) {
                            throw new DomainException(
                                'Relasi lab sudah DELETED tetapi masih menunjuk '
                                .'ke environment. Perlu rekonsiliasi.'
                            );
                        }

                        $attacker = LabEnvironment::query()
                            ->find($lab->attacker_environment_id);

                        if (! $attacker) {
                            throw new DomainException(
                                'Record Attacker tidak ditemukan. '
                                .'Penghapusan pasangan diblokir.'
                            );
                        }

                        $labId = app(
                            \App\Services\GuidedCtf\AdminPairedLabDeletionService::class
                        )->request($admin, $attacker);

                        Notification::make()
                            ->title('Delete Pair dijadwalkan')
                            ->body(
                                'Lab #'.$labId.
                                ': penghapusan Attacker dan Victim sedang diantrikan.'
                            )
                            ->warning()
                            ->send();

                        return;
                    }

                    // Fail closed for orphaned student-style containers.
                    if ($looksPaired($record)) {
                        throw new DomainException(
                            'Container mahasiswa belum memiliki relasi '
                            .'LabSession yang valid. Delete individual ditolak.'
                        );
                    }

                    // Independent infrastructure retains the old lifecycle.
                    if (! self::allows($record, 'delete')) {
                        throw new DomainException(
                            'Environment sedang tidak dapat dihapus.'
                        );
                    }

                    self::dispatch('delete', $record);

                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->title('Delete belum dijalankan')
                        ->body(
                            $exception instanceof DomainException
                                ? $exception->getMessage()
                                : 'Terjadi kesalahan. Periksa log administrator.'
                        )
                        ->danger()
                        ->send();
                }
            });
    }

    private static function allows(LabEnvironment $record, string $action): bool
    {
        if (in_array($action, ['delete', 'reset'], true)
            && AdminEnvironmentPair::forEnvironment((int) $record->getKey()) !== null) {
            return false;
        }
        return in_array($action, app(EnvironmentLifecycleService::class)->allowedActions($record), true);
    }

    /**
     * Records the intention and hands it to the queue.
     *
     * The notification says the work started, never that it succeeded: at this
     * point nothing has happened to the infrastructure yet.
     */
    private static function dispatch(string $action, LabEnvironment $record): void
    {
        try {
            app(EnvironmentLifecycleService::class)->request(auth()->user(), $record, $action);
        } catch (DomainException $exception) {
            Notification::make()
                ->title('Not available')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(match ($action) {
                'reset' => 'Reset started',
                'delete' => 'Deletion started',
                default => ucfirst($action).' started',
            })
            ->body(match ($action) {
                'delete' => 'The container is being destroyed, then the record is removed. It stays listed until both have succeeded.',
                'reset' => 'The container is being destroyed and recreated. Its address will change.',
                default => sprintf(
                    '%s is being converged. This row updates itself while the work runs.',
                    (string) $record->container_name
                ),
            })
            ->warning()
            ->persistent()
            ->send();
    }
}
