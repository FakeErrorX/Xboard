<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Plugin\HookManager;
use App\Utils\CacheKey;
use App\Utils\Helper;
use Illuminate\Support\Facades\Cache;

class LoginService
{
    /**
     * Handle user login
     *
     * @param string $email User email
     * @param string $password User password
     * @return array [Success status, User object or error message]
     */
    public function login(string $email, string $password): array
    {
        // Check password error limit
        if ((int) admin_setting('password_limit_enable', true)) {
            $passwordErrorCount = (int) Cache::get(CacheKey::get('PASSWORD_ERROR_LIMIT', $email), 0);
            if ($passwordErrorCount >= (int) admin_setting('password_limit_count', 5)) {
                return [
                    false,
                    [
                        429,
                        __('There are too many password errors, please try again after :minute minutes.', [
                            'minute' => admin_setting('password_limit_expire', 60)
                        ])
                    ]
                ];
            }
        }

        // Find user
        $user = User::where('email', $email)->first();
        if (!$user) {
            return [false, [400, __('Incorrect email or password')]];
        }

        // Verify password
        if (
            !Helper::multiPasswordVerify(
                $user->password_algo,
                $user->password_salt,
                $password,
                $user->password
            )
        ) {
            // Increase password error count
            if ((int) admin_setting('password_limit_enable', true)) {
                $passwordErrorCount = (int) Cache::get(CacheKey::get('PASSWORD_ERROR_LIMIT', $email), 0);
                Cache::put(
                    CacheKey::get('PASSWORD_ERROR_LIMIT', $email),
                    (int) $passwordErrorCount + 1,
                    60 * (int) admin_setting('password_limit_expire', 60)
                );
            }
            return [false, [400, __('Incorrect email or password')]];
        }

        // Check account status
        if ($user->banned) {
            return [false, [400, __('Your account has been suspended')]];
        }

        // Update last login time
        $user->last_login_at = time();
        $user->save();

        HookManager::call('user.login.after', $user);
        return [true, $user];
    }

    /**
     * Handle password reset
     *
     * @param string $email User email
     * @param string $emailCode Email verification code
     * @param string $password New password
     * @return array [Success status, Result or error message]
     */
    public function resetPassword(string $email, string $emailCode, string $password): array
    {
        // Check reset request limit
        $forgetRequestLimitKey = CacheKey::get('FORGET_REQUEST_LIMIT', $email);
        $forgetRequestLimit = (int) Cache::get($forgetRequestLimitKey);
        if ($forgetRequestLimit >= 3) {
            return [false, [429, __('Reset failed, Please try again later')]];
        }

        // Verify email verification code
        if ((string) Cache::get(CacheKey::get('EMAIL_VERIFY_CODE', $email)) !== (string) $emailCode) {
            Cache::put($forgetRequestLimitKey, $forgetRequestLimit ? $forgetRequestLimit + 1 : 1, 300);
            return [false, [400, __('Incorrect email verification code')]];
        }

        // Find user
        $user = User::where('email', $email)->first();
        if (!$user) {
            return [false, [400, __('This email is not registered in the system')]];
        }

        // Update password
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->password_algo = NULL;
        $user->password_salt = NULL;

        if (!$user->save()) {
            return [false, [500, __('Reset failed')]];
        }

        HookManager::call('user.password.reset.after', $user);

        // Clear email verification code
        Cache::forget(CacheKey::get('EMAIL_VERIFY_CODE', $email));

        return [true, true];
    }


    /**
     * Generate temporary login token and quick login URL
     *
     * @param User $user User object
     * @param string $redirect Redirect path  
     * @return string|null Quick login URL
     */
    public function generateQuickLoginUrl(User $user, ?string $redirect = null): ?string
    {
        if (!$user || !$user->exists) {
            return null;
        }

        $code = Helper::guid();
        $key = CacheKey::get('TEMP_TOKEN', $code);

        Cache::put($key, $user->id, 60);

        $redirect = $redirect ?: 'dashboard';
        $loginRedirect = '/#/login?verify=' . $code . '&redirect=' . rawurlencode($redirect);

        if (admin_setting('app_url')) {
            $url = admin_setting('app_url') . $loginRedirect;
        } else {
            $url = url($loginRedirect);
        }

        return $url;
    }
}