<?php

namespace App\Utils;

class CacheKey
{
    // Core cache key definitions
    const CORE_KEYS = [
        'EMAIL_VERIFY_CODE' => 'Email verification code',
        'LAST_SEND_EMAIL_VERIFY_TIMESTAMP' => 'Last email verification code send timestamp',
        'TEMP_TOKEN' => 'Temporary token',
        'LAST_SEND_EMAIL_REMIND_TRAFFIC' => 'Last traffic email reminder send time',
        'SCHEDULE_LAST_CHECK_AT' => 'Scheduled task last check time',
        'REGISTER_IP_RATE_LIMIT' => 'Registration rate limit',
        'LAST_SEND_LOGIN_WITH_MAIL_LINK_TIMESTAMP' => 'Last login link send timestamp',
        'PASSWORD_ERROR_LIMIT' => 'Password error count limit',
        'USER_SESSIONS' => 'User sessions',
        'FORGET_REQUEST_LIMIT' => 'Password reset request limit'
    ];

    // Allowed cache key patterns (supports wildcards)
    const ALLOWED_PATTERNS = [
        'SERVER_*_ONLINE_USER',        // Server online users
        'MULTI_SERVER_*_ONLINE_USER',  // Multi-server online users
        'SERVER_*_LAST_CHECK_AT',      // Server last check time
        'SERVER_*_LAST_PUSH_AT',       // Server last push time
        'SERVER_*_LOAD_STATUS',        // Server load status
        'SERVER_*_LAST_LOAD_AT',       // Server last load submission time
    ];

    /**
     * Generate cache key
     */
    public static function get(string $key, mixed $uniqueValue = null): string
    {
        // Check if it's a core key
        if (array_key_exists($key, self::CORE_KEYS)) {
            return $uniqueValue ? $key . '_' . $uniqueValue : $key;
        }

        // Check if it matches allowed patterns
        if (self::matchesPattern($key)) {
            return $uniqueValue ? $key . '_' . $uniqueValue : $key;
        }

        // Log warning in development environment, allow in production
        if (app()->environment('local', 'development')) {
            logger()->warning("Unknown cache key used: {$key}");
        }

        return $uniqueValue ? $key . '_' . $uniqueValue : $key;
    }

    /**
     * Check if key name matches allowed patterns
     */
    private static function matchesPattern(string $key): bool
    {
        foreach (self::ALLOWED_PATTERNS as $pattern) {
            $regex = '/^' . str_replace('*', '[A-Z_]+', $pattern) . '$/';
            if (preg_match($regex, $key)) {
                return true;
            }
        }
        return false;
    }
}
