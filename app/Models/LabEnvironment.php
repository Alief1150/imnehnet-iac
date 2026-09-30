<?php

namespace App\Models;

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use Database\Factories\LabEnvironmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student lab: a Linux system container inside Incus on LAB-ENV.
 *
 * LAB-ENV itself is never this record. The record is one container belonging
 * to one student, never the compute plane itself.
 *
 * The table lives in the infrastructure database because the infrastructure
 * automation owns it. Nothing is mirrored into the application schema.
 *
 * There is deliberately no soft delete. This table is a statement about what
 * currently exists on the compute plane, so once an environment is destroyed
 * the record has to go with it. What happened, and when, is kept in the
 * activity log rather than in a tombstone row that could be mistaken for a
 * live environment.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $container_name
 * @property EnvironmentStatus $status
 * @property RuntimeStatus $runtime_status
 * @property string|null $tofu_workspace
 */
class LabEnvironment extends Model
{
    /** @use HasFactory<LabEnvironmentFactory> */
    use HasFactory;

    protected $table = 'lab_environments';

    /**
     * The inventory normally sits in the infrastructure database. The name is
     * read from configuration so the test suite can aim it at its own
     * throwaway database instead of a second in-memory one.
     */
    public function getConnectionName(): ?string
    {
        return (string) config('infrastructure.environment_connection', 'imnehnet');
    }

    /**
     * The infrastructure table is column complete rather than mass-assignment
     * protected: the columns are the contract with the automation, and the
     * only way a value reaches this model is through the lifecycle services,
     * which validate first.
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EnvironmentStatus::class,
            'runtime_status' => RuntimeStatus::class,
            'cpu_limit' => 'integer',
            'memory_limit_mb' => 'integer',
            'disk_limit_gb' => 'integer',
            'terminal_enabled' => 'boolean',
            'provisioned_at' => 'datetime',
        ];
    }

    /**
     * The owner is a portal account in the application database. The
     * relationship crosses connections deliberately: the foreign key lives
     * here, the user row lives there.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Container names become Incus instance names, an OpenTofu workspace
     * suffix and an Ansible extra variable. All three are shell and URL
     * adjacent, so the value is validated to a conservative character set
     * before it is ever used.
     */
    public static function isValidContainerName(string $name): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]{2,62}$/', $name);
    }

    /**
     * One workspace per environment. Deriving the name from the container
     * keeps the mapping reversible, which is what makes reconciliation
     * possible without an extra lookup table.
     */
    public static function workspaceFor(string $containerName): string
    {
        return 'env-'.$containerName;
    }

    /**
     * Runtime is only reported as running when both the runtime state and the
     * lifecycle state agree that the environment is meant to be up.
     */
    public function isRunning(): bool
    {
        return $this->runtime_status === RuntimeStatus::Running;
    }

    public function isSettled(): bool
    {
        return $this->status->isSettled();
    }

    /**
     * True when the portal's record and the last observed container state
     * disagree. Surfaced in the UI instead of silently trusting either side.
     */
    public function hasStateMismatch(): bool
    {
        if ($this->runtime_status === RuntimeStatus::Unknown) {
            return false;
        }

        return match ($this->status) {
            EnvironmentStatus::Ready => $this->runtime_status !== RuntimeStatus::Running,
            EnvironmentStatus::Stopped => $this->runtime_status === RuntimeStatus::Running,
            default => false,
        };
    }

    public function isTerminalAvailable(): bool
    {
        return (bool) $this->terminal_enabled
            && $this->status === EnvironmentStatus::Ready
            && $this->runtime_status === RuntimeStatus::Running;
    }
}
