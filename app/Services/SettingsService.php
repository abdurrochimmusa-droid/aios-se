<?php

namespace App\Services;

use App\Models\Secret;
use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan runtime yang diubah lewat menu Settings.
 *
 * Nilai di tabel settings menimpa bawaan .env/config. Aman dipanggil
 * sebelum migrasi berjalan (mis. saat config di-cache): bila tabel
 * belum ada, langsung kembalikan bawaan.
 */
class SettingsService
{
    public const DEFAULTS = [
        'nine_router.base_url' => 'http://localhost:8787/v1',
        'nine_router.default_combo' => 'combo-hemat',
        'nine_router.timeout' => '120',
        'aios.task_token_budget' => '200000',
        'aios.delegation_max_depth' => '4',
        'aios.delegation_max_rounds' => '5',
        'aios.projects_root' => '',
        'nine_router.mock' => '',
    ];

    /** @var array<string, ?string>|null */
    private ?array $cache = null;

    public function get(string $key, ?string $fallback = null): ?string
    {
        $cache = $this->loaded();

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        return $fallback ?? self::DEFAULTS[$key] ?? null;
    }

    public function getInt(string $key, ?int $fallback = null): int
    {
        $value = $this->get($key, $fallback !== null ? (string) $fallback : null);

        return (int) ($value ?? $fallback ?? 0);
    }

    public function getBool(string $key, bool $fallback = false): bool
    {
        $value = $this->loaded()[$key] ?? null;

        if ($value === null || $value === '') {
            return $fallback;
        }

        return in_array(strtolower($value), ['1', 'true', 'ya'], true);
    }

    /** @param array<string, ?string> $data */
    public function setMany(array $data): void
    {
        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->cache = null;
    }

    public function getApiKey(): ?string
    {
        $secret = Secret::forScope('global')->where('key_name', 'ninerouter_api_key')->first();

        if ($secret !== null) {
            return $secret->value;
        }

        $env = (string) config('aios.nine_router.api_key');

        return $env === '' ? null : $env;
    }

    public function setApiKey(string $key): void
    {
        Secret::updateOrCreate(
            ['scope' => 'global', 'scope_id' => null, 'key_name' => 'ninerouter_api_key'],
            ['value' => $key]
        );
    }

    public function hasApiKey(): bool
    {
        return $this->getApiKey() !== null;
    }

    /** @return array<string, ?string> */
    private function loaded(): array
    {
        if ($this->cache === null) {
            $this->cache = $this->loadAll();
        }

        return $this->cache;
    }

    /** @return array<string, ?string> */
    private function loadAll(): array
    {
        try {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return Setting::pluck('value', 'key')->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
