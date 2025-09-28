<?php

namespace App\Services\Auth;

use App\Models\InviteCode;
use App\Models\Plan;
use App\Models\User;
use App\Services\CaptchaService;
use App\Services\Plugin\HookManager;
use App\Services\UserService;
use App\Utils\CacheKey;
use App\Utils\Dict;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RegisterService
{
    /**
     * Verify user registration request
     *
     * @param Request $request Request object
     * @return array [Whether passed, Error message]
     */
    public function validateRegister(Request $request): array
    {
        // Check IP registration limit
        if ((int) admin_setting('register_limit_by_ip_enable', 0)) {
            $registerCountByIP = Cache::get(CacheKey::get('REGISTER_IP_RATE_LIMIT', $request->ip())) ?? 0;
            if ((int) $registerCountByIP >= (int) admin_setting('register_limit_count', 3)) {
                return [
                    false,
                    [
                        429,
                        __('Register frequently, please try again after :minute minute', [
                            'minute' => admin_setting('register_limit_expire', 60)
                        ])
                    ]
                ];
            }
        }

        // Check verification code
        $captchaService = app(CaptchaService::class);
        [$captchaValid, $captchaError] = $captchaService->verify($request);
        if (!$captchaValid) {
            return [false, $captchaError];
        }

        // Check email whitelist
        if ((int) admin_setting('email_whitelist_enable', 0)) {
            if (
                !Helper::emailSuffixVerify(
                    $request->input('email'),
                    admin_setting('email_whitelist_suffix', Dict::EMAIL_WHITELIST_SUFFIX_DEFAULT)
                )
            ) {
                return [false, [400, __('Email suffix is not in the Whitelist')]];
            }
        }

        // Check Gmail restriction
        if ((int) admin_setting('email_gmail_limit_enable', 0)) {
            $prefix = explode('@', $request->input('email'))[0];
            if (strpos($prefix, '.') !== false || strpos($prefix, '+') !== false) {
                return [false, [400, __('Gmail alias is not supported')]];
            }
        }

        // Check if registration is disabled
        if ((int) admin_setting('stop_register', 0)) {
            return [false, [400, __('Registration has closed')]];
        }

        // Check invite code requirement
        if ((int) admin_setting('invite_force', 0)) {
            if (empty($request->input('invite_code'))) {
                return [false, [422, __('You must use the invitation code to register')]];
            }
        }

        // Check email verification
        if ((int) admin_setting('email_verify', 0)) {
            if (empty($request->input('email_code'))) {
                return [false, [422, __('Email verification code cannot be empty')]];
            }
            if ((string) Cache::get(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('email'))) !== (string) $request->input('email_code')) {
                return [false, [400, __('Incorrect email verification code')]];
            }
        }

        // Check if email exists
        $email = $request->input('email');
        $exist = User::where('email', $email)->first();
        if ($exist) {
            return [false, [400201, __('Email already exists')]];
        }

        return [true, null];
    }

    /**
     * Handle invite code
     *
     * @param string $inviteCode Invite code
     * @return int|null Inviter ID
     */
    public function handleInviteCode(string $inviteCode): int|null
    {
        $inviteCodeModel = InviteCode::where('code', $inviteCode)
            ->where('status', InviteCode::STATUS_UNUSED)
            ->first();

        if (!$inviteCodeModel) {
            if ((int) admin_setting('invite_force', 0)) {
                throw new \Exception(__('Invalid invitation code'));
            }
            return null;
        }

        if (!(int) admin_setting('invite_never_expire', 0)) {
            $inviteCodeModel->status = InviteCode::STATUS_USED;
            $inviteCodeModel->save();
        }

        return $inviteCodeModel->user_id;
    }



    /**
     * Register user
     *
     * @param Request $request Request object
     * @return array [Success status, User object or error message]
     */
    public function register(Request $request): array
    {
        // Validate registration data
        [$valid, $error] = $this->validateRegister($request);
        if (!$valid) {
            return [false, $error];
        }

        HookManager::call('user.register.before', $request);

        $email = $request->input('email');
        $password = $request->input('password');
        $inviteCode = $request->input('invite_code');

        // Handle invite code to get inviter ID
        $inviteUserId = null;
        if ($inviteCode) {
            $inviteUserId = $this->handleInviteCode($inviteCode);
        }

        // Create user
        $userService = app(UserService::class);
        $user = $userService->createUser([
            'email' => $email,
            'password' => $password,
            'invite_user_id' => $inviteUserId,
        ]);

        // Save user
        if (!$user->save()) {
            return [false, [500, __('Register failed')]];
        }

        HookManager::call('user.register.after', $user);

        // Clear email verification code
        if ((int) admin_setting('email_verify', 0)) {
            Cache::forget(CacheKey::get('EMAIL_VERIFY_CODE', $email));
        }

        // Update last login time
        $user->last_login_at = time();
        $user->save();

        // Update IP registration count
        if ((int) admin_setting('register_limit_by_ip_enable', 0)) {
            $registerCountByIP = Cache::get(CacheKey::get('REGISTER_IP_RATE_LIMIT', $request->ip())) ?? 0;
            Cache::put(
                CacheKey::get('REGISTER_IP_RATE_LIMIT', $request->ip()),
                (int) $registerCountByIP + 1,
                (int) admin_setting('register_limit_expire', 60) * 60
            );
        }

        return [true, $user];
    }
}