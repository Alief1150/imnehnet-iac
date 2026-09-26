<?php

namespace App\Observers;

use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Database\Eloquent\Model;

/**
 * Audits the fields that decide what an account may do. Done on the model
 * rather than on the Filament page so the entry is written no matter which
 * surface performed the change.
 */
final class UserObserver
{
    /**
     * Only these are audited; a name or email correction is not interesting
     * operationally and would drown the log.
     *
     * @var list<string>
     */
    private const AUDITED = ['role', 'status'];

    public function updated(Model $model): void
    {
        if (! $model instanceof User) {
            return;
        }

        $changes = [];

        foreach (self::AUDITED as $field) {
            $before = $model->getOriginal($field);
            $after = $model->getAttribute($field);

            $before = $before instanceof \BackedEnum ? (string) $before->value : (is_scalar($before) ? (string) $before : null);
            $after = $after instanceof \BackedEnum ? (string) $after->value : (is_scalar($after) ? (string) $after : null);

            if ($before !== $after) {
                $changes[$field] = ['from' => $before, 'to' => $after];
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
}
