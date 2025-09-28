<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ConfigSave extends FormRequest
{
    const RULES = [
        // invite & commission
        'invite_force' => '',
        'invite_commission' => 'integer|nullable',
        'invite_gen_limit' => 'integer|nullable',
        'invite_never_expire' => '',
        'commission_first_time_enable' => '',
        'commission_auto_check_enable' => '',
        'commission_withdraw_limit' => 'nullable|numeric',
        'commission_withdraw_method' => 'nullable|array',
        'withdraw_close_enable' => '',
        'commission_distribution_enable' => '',
        'commission_distribution_l1' => 'nullable|numeric',
        'commission_distribution_l2' => 'nullable|numeric',
        'commission_distribution_l3' => 'nullable|numeric',
        // site
        'logo' => 'nullable|url',
        'force_https' => '',
        'stop_register' => '',
        'app_name' => '',
        'app_description' => '',
        'app_url' => 'nullable|url',
        'subscribe_url' => 'nullable',
        'try_out_enable' => '',
        'try_out_plan_id' => 'integer',
        'try_out_hour' => 'numeric',
        'tos_url' => 'nullable|url',
        'currency' => '',
        'currency_symbol' => '',
        // subscribe
        'plan_change_enable' => '',
        'reset_traffic_method' => 'in:0,1,2,3,4',
        'surplus_enable' => '',
        'new_order_event_id' => '',
        'renew_order_event_id' => '',
        'change_order_event_id' => '',
        'show_info_to_server_enable' => '',
        'show_protocol_to_server_enable' => '',
        'subscribe_path' => '',
        // server
        'server_token' => 'nullable|min:16',
        'server_pull_interval' => 'integer',
        'server_push_interval' => 'integer',
        'device_limit_mode' => 'integer',
        // frontend
        'frontend_theme' => '',
        'frontend_theme_sidebar' => 'nullable|in:dark,light',
        'frontend_theme_header' => 'nullable|in:dark,light',
        'frontend_theme_color' => 'nullable|in:default,darkblue,black,green',
        'frontend_background_url' => 'nullable|url',
        // email
        'email_template' => '',
        'email_host' => '',
        'email_port' => '',
        'email_username' => '',
        'email_password' => '',
        'email_encryption' => '',
        'email_from_address' => '',
        'remind_mail_enable' => '',
        // telegram
        'telegram_bot_enable' => '',
        'telegram_bot_token' => '',
        'telegram_discuss_id' => '',
        'telegram_channel_id' => '',
        'telegram_discuss_link' => 'nullable|url',
        // app
        'windows_version' => '',
        'windows_download_url' => '',
        'macos_version' => '',
        'macos_download_url' => '',
        'android_version' => '',
        'android_download_url' => '',
        // safe
        'email_whitelist_enable' => 'boolean',
        'email_whitelist_suffix' => 'nullable|array',
        'email_gmail_limit_enable' => 'boolean',
        'captcha_enable' => 'boolean',
        'captcha_type' => 'in:recaptcha,turnstile,recaptcha-v3',
        'recaptcha_enable' => 'boolean',
        'recaptcha_key' => '',
        'recaptcha_site_key' => '',
        'recaptcha_v3_secret_key' => '',
        'recaptcha_v3_site_key' => '',
        'recaptcha_v3_score_threshold' => 'numeric|min:0|max:1',
        'turnstile_secret_key' => '',
        'turnstile_site_key' => '',
        'email_verify' => 'bool',
        'safe_mode_enable' => 'boolean',
        'register_limit_by_ip_enable' => 'boolean',
        'register_limit_count' => 'integer',
        'register_limit_expire' => 'integer',
        'secure_path' => 'min:5|regex:/^[\w-]*$/',
        'password_limit_enable' => 'boolean',
        'password_limit_count' => 'integer',
        'password_limit_expire' => 'integer',
        'default_remind_expire' => 'boolean',
        'default_remind_traffic' => 'boolean',
        'subscribe_template_singbox' => 'nullable',
        'subscribe_template_clash' => 'nullable',
        'subscribe_template_clashmeta' => 'nullable',
        'subscribe_template_stash' => 'nullable',
        'subscribe_template_surge' => 'nullable',
        'subscribe_template_surfboard' => 'nullable'
    ];
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return self::RULES;
    }

    public function messages()
    {
        // validation prompt
        return [
            'app_url.url' => 'Site URL format is incorrect, must include http(s)://',
            'subscribe_url.url' => 'Subscribe URL format is incorrect, must include http(s)://',
            'server_token.min' => 'Communication key length must be greater than 16 characters',
            'tos_url.url' => 'Terms of Service URL format is incorrect, must include http(s)://',
            'telegram_discuss_link.url' => 'Telegram group address must be a URL format, must include http(s)://',
            'logo.url' => 'LOGO URL format is incorrect, must include https(s)://',
            'secure_path.min' => 'Admin path length must be at least 5 characters',
            'secure_path.regex' => 'Admin path can only contain letters or numbers',
            'captcha_type.in' => 'Captcha type can only be recaptcha, turnstile or recaptcha-v3',
            'recaptcha_v3_score_threshold.numeric' => 'reCAPTCHA v3 score threshold must be a number',
            'recaptcha_v3_score_threshold.min' => 'reCAPTCHA v3 score threshold cannot be less than 0',
            'recaptcha_v3_score_threshold.max' => 'reCAPTCHA v3 score threshold cannot be greater than 1'
        ];
    }
}
