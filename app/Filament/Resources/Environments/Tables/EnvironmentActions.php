<?php

namespace App\Filament\Resources\Environments\Tables;

use App\Contracts\LifecycleDriver;
use App\Models\LabEnvironment;
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
            self::delete(),
        ];
    }

    private static function start(): Action
    {
        return Action::make('start')
            ->label('Start')
            ->icon(Lucide::icon('play'))
            ->color('success')
            ->tooltip('Converge the container onto running')
            ->visible(fn (LabEnvironment $record): bool => self::allows($record, 'start'))
            ->action(fn (LabEnvironment $record) => self::dispatch('start', $record));
    }

    private static function stop(): Action
    {
        return Action::make('stop')
            ->label('Stop')
            ->icon(Lucide::icon('square'))
            ->color('gray')
            ->tooltip('Converge the container onto stopped')
            ->visible(fn (LabEnvironment $record): bool => self::allows($record, 'stop'))
            ->action(fn (LabEnvironment $record) => self::dispatch('stop', $record));
    }

    private static function reset(): Action
    {
        return Action::make('reset')
            ->label('Reset')
            ->icon(Lucide::icon('rotate-ccw'))
            ->color('warning')
            // Reset destroys the container and builds it again, so the address it
            // returns with is not the one an operator may have written down.
            // That has to be said before the button is pressed.
            ->requiresConfirmation()
            ->modalHeading('Reset this environment?')
            ->modalDescription('The container is destroyed and recreated from the same configuration. '
                .'Everything inside it is lost, and the internal IP address will change.')
            ->modalSubmitActionLabel('Reset environment')
            ->visible(fn (LabEnvironment $record): bool => self::allows($record, 'reset'))
            ->action(fn (LabEnvironment $record) => self::dispatch('reset', $record));
    }

    /**
     * Read-only. Reads what the infrastructure currently believes and updates
     * only the observed runtime, never the desired status, so a disagreement is
     * shown rather than quietly resolved in favour of one side.
     */
    private static function refresh(): Action
    {
        return Action::make('refresh')
            ->label('Refresh status')
            ->icon(Lucide::icon('refresh-cw'))
            ->color('gray')
            ->tooltip('Read the actual state without changing anything')
            ->visible(fn (LabEnvironment $record): bool => self::allows($record, 'refresh'))
            ->action(function (LabEnvironment $record): void {
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

    private static function delete(): Action
    {
        return Action::make('delete')
            ->label('Delete')
            ->icon(Lucide::icon('trash'))
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete this environment?')
            ->modalDescription('The container is destroyed on the compute plane first. Only once that '
                .'succeeds is the record removed, so a failed destruction leaves the environment listed '
                .'with the reason recorded.')
            ->modalSubmitActionLabel('Delete environment')
            ->visible(fn (LabEnvironment $record): bool => self::allows($record, 'delete'))
            ->action(fn (LabEnvironment $record) => self::dispatch('delete', $record));
    }

    private static function allows(LabEnvironment $record, string $action): bool
    {
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
