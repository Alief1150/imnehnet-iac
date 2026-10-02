<?php

namespace App\Observers;

use App\Models\LabEnvironment;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Database\Eloquent\Model;

/**
 * Audits the fields that decide what an account is allowed to do. Recorded on
 * the model rather than on the Filament page, so the entry is written no
 * matter which surface performed the change.
 */
final class UserObserver
{
    /**
     * Only these are audited. A name or email correction is not interesting
     * operationally and would drown the log.
     *
     * @var list<string>
     */
    private const AUDITED = ['role', 'status'];

    /**
     * Pre-save snapshots, keyed by model key.
     *
     * This has to be static: the event dispatcher resolves a `Class@method`
     * listener into a brand new object on every dispatch, so a property on the
     * observer is already empty by the time the next model event fires. A
     * static map is the only state that survives for the length of a request.
     *
     * @var array<string, array<string, string>>
     */
    private static array $before = [];

    /**
     * Snapshot taken while the record still holds its previous values, which
     * is the only point before the write where they are readable.
     *
     * Only existing records are captured. A create has nothing to compare
     * against and would otherwise leave a snapshot behind that is never used.
     */
    public function saving(Model $model): void
    {
        if (! $model instanceof User || ! $model->exists) {
            return;
        }

        $snapshot = [];

        foreach (self::AUDITED as $field) {
            $snapshot[$field] = self::asString($model->getRawOriginal($field));
        }

        self::$before[(string) $model->getKey()] = $snapshot;
    }

    /**
     * Written only after the write reached the database, so the audit trail
     * never claims a change that was rolled back.
     */
    /**
     * An account's environments become unassigned rather than pointing at an
     * account that no longer exists.
     *
     * Leaving the id behind would be a dangling reference: the row would keep
     * claiming an owner that cannot be signed in to, and if that id were ever
     * reused by a new account the new owner would silently inherit someone
     * else's environment. Unassigning is also the safe direction, because the
     * container keeps running and remains listed for an administrator to deal
     * with, rather than being destroyed as a side effect of deleting a login.
     *
     * The inventory lives in a different database, so the connection is named
     * explicitly. Going through the model would use the same connection, but
     * saying it here is what makes the cross-database write visible.
     */
    public function deleting(User $user): void
    {
        LabEnvironment::query()
            ->where('user_id', $user->getKey())
            ->update(['user_id' => null]);
    }

    public function updated(Model $model): void
    {
        if (! $model instanceof User) {
            return;
        }

        $key = (string) $model->getKey();
        $before = self::$before[$key] ?? null;

        // Cleared whatever the outcome, so an aborted write cannot leave a
        // stale snapshot behind for the next save of the same record.
        unset(self::$before[$key]);

        if ($before === null) {
            return;
        }

        $changes = [];

        foreach (self::AUDITED as $field) {
            $after = self::asString($model->getAttribute($field));

            if ($before[$field] !== $after) {
                $changes[$field] = ['from' => $before[$field], 'to' => $after];
            }
        }

        if ($changes === []) {
            return;
        }

        $described = [];

        foreach ($changes as $field => $change) {
            $described[] = sprintf('%s from %s to %s', $field, $change['from'], $change['to']);
        }

        app(ActivityLogService::class)->record(
            actor: auth()->user(),
            action: 'UPDATE_USER',
            resource: $model,
            status: 'completed',
            description: sprintf('Updated %s: %s.', $model->email, implode('; ', $described)),
            metadata: [
                'changed_fields' => array_keys($changes),
                'previous_values' => $changes,
            ],
        );
    }

    private static function asString(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
