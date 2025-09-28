<?php

namespace App\Services\Auth;

use App\Jobs\SendEmailJob;
use App\Models\User;
use App\Utils\CacheKey;
use App\Utils\Helper;
use Illuminate\Support\Facades\Cache;

class MailLinkService
{
    /**
     * Handle email link login logic
     *
     * @param string $email User email
     * @param string|null $redirect Redirect address
     * @return array Returns processing result
     */
    public function handleMailLink(string $email, ?string $redirect = null): array
    {
        if (!(int) admin_setting('login_with_mail_link_enable')) {
            return [false, [404, null]];
        }

        if (Cache::get(CacheKey::get('LAST_SEND_LOGIN_WITH_MAIL_LINK_TIMESTAMP', $email))) {
            return [false, [429, __('Sending frequently, please try again later')]];
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return [true, true]; // Success but user does not exist, protect user privacy
        }

        $code = Helper::guid();
        $key = CacheKey::get('TEMP_TOKEN', $code);
        Cache::put($key, $user->id, 300);
        Cache::put(CacheKey::get('LAST_SEND_LOGIN_WITH_MAIL_LINK_TIMESTAMP', $email), time(), 60);

        $redirectUrl = '/#/login?verify=' . $code . '&redirect=' . ($redirect ? $redirect : 'dashboard');
        if (admin_setting('app_url')) {
            $link = admin_setting('app_url') . $redirectUrl;
        } else {
            $link = url($redirectUrl);
        }

        $this->sendMailLinkEmail($user, $link);

        return [true, $link];
    }

    /**
     * Send email link login email
     *
     * @param User $user User object
     * @param string $link Login link
     * @return void
     */
    private function sendMailLinkEmail(User $user, string $link): void
    {
        SendEmailJob::dispatch([
            'email' => $user->email,
            'subject' => __('Login to :name', [
                'name' => admin_setting('app_name', 'XBoard')
            ]),
            'template_name' => 'login',
            'template_value' => [
                'name' => admin_setting('app_name', 'XBoard'),
                'link' => $link,
                'url' => admin_setting('app_url')
            ]
        ]);
    }

    /**
     * Handle Token login
     *
     * @param string $token Login token
     * @return int|null User ID or null
     */
    public function handleTokenLogin(string $token): ?int
    {
        $key = CacheKey::get('TEMP_TOKEN', $token);
        $userId = Cache::get($key);

        if (!$userId) {
            return null;
        }

        $user = User::find($userId);

        if (!$user || $user->banned) {
            return null;
        }

        Cache::forget($key);

        return $userId;
    }
}