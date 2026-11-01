<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Akses terpusat ke pengaturan platform yang disimpan di database.
 *
 * Setiap kunci dibaca dari cache selama TTL singkat. Penulisan memperbarui
 * database dan cache sekaligus, sehingga backend layanan selalu membaca
 * nilai efektif terbaru tanpa sentuh tabel pada setiap permintaan.
 *
 * Jika kunci belum ada di database, nilai default dari PlatformSetting::schema()
 * digunakan dan ditulis ke database pada penulisan pertama.
 */
final class SettingsService
{
    private const CACHE_TTL_SECONDS = 60;

    private const CACHE_PREFIX = 'platform_setting.';

    /**
     * Baca nilai pengaturan, dikembalikan dalam tipe PHP aslinya.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = self::CACHE_PREFIX.$key;

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $setting = PlatformSetting::query()->where('key', $key)->first();

        if ($setting === null) {
            $schema = PlatformSetting::schema();
            $entry = $schema[$key] ?? null;

            if ($entry === null) {
                return $default;
            }

            $value = $entry['default'];

            $this->put($key, $value, persist: true);

            return $value;
        }

        $value = $setting->getTypedValue();

        Cache::put($cacheKey, $value, self::CACHE_TTL_SECONDS);

        return $value;
    }

    /**
     * Tulis nilai pengaturan ke database dan perbarui cache.
     *
     * @param  bool  $persist  Tetapkan ke database (selalu true kecuali pengujian)
     */
    public function put(string $key, mixed $value, bool $persist = true): void
    {
        $cacheKey = self::CACHE_PREFIX.$key;

        Cache::put($cacheKey, $value, self::CACHE_TTL_SECONDS);

        if (! $persist) {
            return;
        }

        $schema = PlatformSetting::schema();
        $entry = $schema[$key] ?? null;

        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                'type' => $entry['type'] ?? (is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : 'string')),
                'group' => $entry['group'] ?? 'general',
                'description' => $entry['description'] ?? null,
                'updated_at' => Carbon::now(),
            ],
        );
    }

    /**
     * Hapus pengaturan dan cache-nya, kembali ke default schema.
     */
    public function forget(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX.$key);
        PlatformSetting::query()->where('key', $key)->delete();
    }

    /**
     * Baca semua pengaturan sebagai array asosiatif kunci => nilai.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $result = [];

        foreach (PlatformSetting::schema() as $key => $entry) {
            $result[$key] = $this->get($key, $entry['default']);
        }

        return $result;
    }

    /**
     * Hapus semua cache pengaturan agar dibaca ulang dari database.
     */
    public function clearCache(): void
    {
        foreach (PlatformSetting::schema() as $key => $_) {
            Cache::forget(self::CACHE_PREFIX.$key);
        }
    }
}
