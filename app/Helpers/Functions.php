<?php
use App\Support\Setting;
use Illuminate\Support\Facades\App;

if (!function_exists('admin_setting')) {
    /**
     * Get or save configuration parameter.
     *
     * @param  string|array  $key
     * @param  mixed  $default
     * @return App\Support\Setting|mixed
     */
    function admin_setting($key = null, $default = null)
    {
        $setting = app(Setting::class);

        if ($key === null) {
            return $setting->toArray();
        }

        if (is_array($key)) {
            $setting->save($key);
            return '';
        }

        $default = config('xboard.' . $key) ?? $default;
        return $setting->get($key) ?? $default;
    }
}

if (!function_exists('admin_settings_batch')) {
    /**
     * Batch get configuration parameters, performance optimized version
     *
     * @param array $keys Configuration key array
     * @return array Returns key-value pair array
     */
    function admin_settings_batch(array $keys): array
    {
        return app(Setting::class)->getBatch($keys);
    }
}

if (!function_exists('source_base_url')) {
    /**
     * Get source base URL, prioritize Referer, then Host
     * @param string $path
     * @return string
     */
    function source_base_url(string $path = ''): string
    {
        $baseUrl = '';
        $referer = request()->header('Referer');

        if ($referer) {
            $parsedUrl = parse_url($referer);
            if (isset($parsedUrl['scheme']) && isset($parsedUrl['host'])) {
                $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
                if (isset($parsedUrl['port'])) {
                    $baseUrl .= ':' . $parsedUrl['port'];
                }
            }
        }

        if (!$baseUrl) {
            $baseUrl = request()->getSchemeAndHttpHost();
        }

        $baseUrl = rtrim($baseUrl, '/');
        $path = ltrim($path, '/');
        return $baseUrl . '/' . $path;
    }
}
