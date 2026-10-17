<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-editable settings with defaults from config('mentionai.defaults').
 * Resolve from the container (singleton) or use the `settings()` helper.
 */
class Settings
{
    private const CACHE_KEY = 'mentionai.settings';

    private ?array $values = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    public function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        $this->flush();
    }

    /** @param  array<string, mixed>  $values */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        $this->flush();
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->values ??= array_merge(
            config('mentionai.defaults', []),
            Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all()),
        );
    }

    public function flush(): void
    {
        $this->values = null;
        Cache::forget(self::CACHE_KEY);
    }
}
