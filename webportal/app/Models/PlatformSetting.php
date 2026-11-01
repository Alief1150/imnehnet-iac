<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide runtime settings stored in the database.
 *
 * These override config file defaults so an administrator can change
 * platform behaviour without editing .env or restarting services.
 * Backend services should read through SettingsService, not read this
 * model directly, so the cache layer is consistent.
 */
final class PlatformSetting extends Model
{



    // im-settings-primary-key-v1

    protected $primaryKey = 'key';



    protected $keyType = 'string';



    public $incrementing = false;



    public const UPDATED_AT = null;

    protected $table = 'platform_settings';

    protected $fillable = ['key', 'value', 'type', 'group', 'description'];

    protected $casts = [
        'type' => 'string',
        'group' => 'string',
    ];

    /**
     * Get the setting value cast to its declared PHP type.
     */
    public function getTypedValue(): mixed
    {
        return match ($this->type) {
            'integer' => (int) ($this->value ?? 0),
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            default => $this->value,
        };
    }

    /**
     * Set the value from a PHP-native value, storing the type too.
     */
    public function setTypedValue(mixed $value): void
    {
        $this->type = match (true) {
            is_int($value) => 'integer',
            is_bool($value) => 'boolean',
            default => 'string',
        };

        $this->value = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
    }

    /**
     * All registered setting keys with defaults, types, groups, and labels.
     *
     * @return array<string, array{type: string, group: string, label: string, description: string, default: mixed}>
     */
    public static function schema(): array
    {
        return [
            // Lab policy
            'student_environment_limit' => [
                'type' => 'integer',
                'group' => 'lab',
                'label' => 'Batas lab aktif per mahasiswa',
                'description' => 'Maksimal banyak lingkungan praktikum yang boleh ditahan satu mahasiswa sekaligus. Administrador tidak terbatasi.',
                'default' => 3,
            ],
            'lab_creation_enabled' => [
                'type' => 'boolean',
                'group' => 'lab',
                'label' => 'Izinkan pembuatan lab baru',
                'description' => 'Jika dimatikan, mahasiswa tidak dapat membuat lab baru hingga kembali diaktifkan.',
                'default' => true,
            ],
            // Terminal policy
            'terminal_session_ttl_minutes' => [
                'type' => 'integer',
                'group' => 'terminal',
                'label' => 'Durasi sesi terminal (menit)',
                'description' => 'Berapa lama sesi shell web boleh tetap aktif sebelum kedaluwarsa.',
                'default' => 15,
            ],
            'terminal_max_sessions_per_user' => [
                'type' => 'integer',
                'group' => 'terminal',
                'label' => 'Maksimal sesi terminal per pengguna',
                'description' => 'Banyak sesi shell web yang boleh diletakkan satu pengguna sekaligus.',
                'default' => 2,
            ],
            'terminal_browser_access_enabled' => [
                'type' => 'boolean',
                'group' => 'terminal',
                'label' => 'Akses terminal melalui browser',
                'description' => 'Jika dimatikan, shell tetap dapat berjalan di manajemen namun tidak ada alamat browser yang diberikan.',
                'default' => false,
            ],
            // Container limits
            'default_cpu_limit' => [
                'type' => 'integer',
                'group' => 'lab',
                'label' => 'Batas CPU per container',
                'description' => 'Batas CPU yang diterapkan pada setiap lingkungan praktikum yang dibuat portal.',
                'default' => 2,
            ],
            'default_memory_limit_mb' => [
                'type' => 'integer',
                'group' => 'lab',
                'label' => 'Batas memori per container (MB)',
                'description' => 'Batas memori yang diterapkan pada setiap lingkungan praktikum yang dibuat portal.',
                'default' => 2048,
            ],
            'default_disk_limit_gb' => [
                'type' => 'integer',
                'group' => 'lab',
                'label' => 'Batas disk per container (GB)',
                'description' => 'Batas disk yang diterapkan pada setiap lingkungan praktikum yang dibuat portal.',
                'default' => 8,
            ],
            // Provisioning
            'provisioning_timeout_seconds' => [
                'type' => 'integer',
                'group' => 'lab',
                'label' => 'Timeout provisioning (detik)',
                'description' => 'Maksimal waktu perintah OpenTofu/Ansible boleh berjalan sebelum dibatalkan.',
                'default' => 900,
            ],
        ];
    }
}
