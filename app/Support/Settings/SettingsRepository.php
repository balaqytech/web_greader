<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Cached read/write access to the `settings` table.
 *
 * The whole table is cached as one map rather than one entry per key. That is deliberate:
 * `Cache::remember()` treats a NULL result as a cache miss and re-queries every call, and
 * "unset" (NULL) is precisely the steady state of an unconfigured setting — per-key caching
 * would therefore hit the database on exactly the reads we make most often. Caching the map
 * also keeps "key present but NULL" distinguishable from "key absent", and the table is
 * small enough that one query is cheaper than N.
 *
 * Any write invalidates the whole map. Settings change rarely; correctness beats a
 * micro-optimised partial invalidation that could leave a stale fee in place.
 */
class SettingsRepository
{
    private const CACHE_KEY = 'settings:map';

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => Setting::query()->pluck('value', 'key')->all()
        );
    }

    public function get(string $key): ?string
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * True when the key exists AND holds a non-NULL value. A key seeded as NULL is
     * "declared but not configured" and reports false here.
     */
    public function isConfigured(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function set(string $key, ?string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
