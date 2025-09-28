<?php

namespace App\Support;

use App\Models\Setting as SettingModel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\Repository;

class Setting
{
    const CACHE_KEY = 'admin_settings';

    private Repository $cache;
    private ?array $loadedSettings = null; // Request-level cache

    public function __construct()
    {
        $this->cache = Cache::store('redis');
    }

    /**
     * Get configuration.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->load();
        return Arr::get($this->loadedSettings, strtolower($key), $default);
    }

    /**
     * Set configuration information.
     */
    public function set(string $key, mixed $value = null): bool
    {
        SettingModel::createOrUpdate(strtolower($key), $value);
        $this->flush();
        return true;
    }

    /**
     * Save configuration to database.
     */
    public function save(array $settings): bool
    {
        foreach ($settings as $key => $value) {
            SettingModel::createOrUpdate(strtolower($key), $value);
        }
        $this->flush();
        return true;
    }

    /**
     * Delete configuration information
     */
    public function remove(string $key): bool
    {
        SettingModel::where('name', $key)->delete();
        $this->flush();
        return true;
    }

    /**
     * Update single setting item
     */
    public function update(string $key, $value): bool
    {
        return $this->set($key, $value);
    }
    
    /**
     * Batch get configuration items
     */
    public function getBatch(array $keys): array
    {
        $this->load();
        $result = [];
        
        foreach ($keys as $index => $item) {
            $isNumericIndex = is_numeric($index);
            $key = strtolower($isNumericIndex ? $item : $index);
            $default = $isNumericIndex ? config('xboard.' . $item) : (config('xboard.' . $key) ?? $item);
            
            $result[$item] = Arr::get($this->loadedSettings, $key, $default);
        }
        
        return $result;
    }
    
    /**
     * Convert all settings to array
     */
    public function toArray(): array
    {
        $this->load();
        return $this->loadedSettings;
    }

    /**
     * Load configuration to request-level cache
     */
    private function load(): void
    {
        if ($this->loadedSettings !== null) {
            return;
        }

        try {
            $settings = $this->cache->rememberForever(self::CACHE_KEY, function (): array {
                return array_change_key_case(
                    SettingModel::pluck('value', 'name')->toArray(),
                    CASE_LOWER
                );
            });
            
            // Handle JSON format values
            foreach ($settings as $key => $value) {
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $settings[$key] = $decoded;
                    }
                }
            }
            
            $this->loadedSettings = $settings;
        } catch (\Throwable) {
            $this->loadedSettings = [];
        }
    }

    /**
     * Clear cache
     */
    private function flush(): void
    {
        $this->cache->forget(self::CACHE_KEY);
        $this->loadedSettings = null;
    }
}
