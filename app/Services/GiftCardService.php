<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Http\Resources\PlanResource;
use App\Models\GiftCardCode;
use App\Models\GiftCardTemplate;
use App\Models\GiftCardUsage;
use App\Models\Plan;
use App\Models\TrafficResetLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GiftCardService
{
    protected readonly GiftCardCode $code;
    protected readonly GiftCardTemplate $template;
    protected ?User $user = null;

    public function __construct(string $code)
    {
        $this->code = GiftCardCode::where('code', $code)->first()
            ?? throw new ApiException('Redeem code does not exist');

        $this->template = $this->code->template;
    }

    /**
     * Set user for redemption
     */
    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Validate redemption code
     */
    public function validate(): self
    {
        $this->validateIsActive();

        $eligibility = $this->checkUserEligibility();
        if (!$eligibility['can_redeem']) {
            throw new ApiException($eligibility['reason']);
        }

        return $this;
    }

    /**
     * Validate if gift card itself is available (does not check user conditions)
     * @throws ApiException
     */
    public function validateIsActive(): self
    {
        if (!$this->template->isAvailable()) {
            throw new ApiException('This gift card type has been disabled');
        }

        if (!$this->code->isAvailable()) {
            throw new ApiException('Redeem code unavailable: ' . $this->code->status_name);
        }
        return $this;
    }

    /**
     * Check if user meets redemption conditions (does not throw exceptions)
     */
    public function checkUserEligibility(): array
    {
        if (!$this->user) {
            return [
                'can_redeem' => false,
                'reason' => 'User information not provided'
            ];
        }

        if (!$this->template->checkUserConditions($this->user)) {
            return [
                'can_redeem' => false,
                'reason' => 'You do not meet the conditions for using this gift card'
            ];
        }

        if (!$this->template->checkUsageLimit($this->user)) {
            return [
                'can_redeem' => false,
                'reason' => 'You have reached the usage limit for this gift card'
            ];
        }

        return ['can_redeem' => true, 'reason' => null];
    }

    /**
     * Use gift card
     */
    public function redeem(array $options = []): array
    {
        if (!$this->user) {
            throw new ApiException('User not set');
        }

        return DB::transaction(function () use ($options) {
            $actualRewards = $this->template->calculateActualRewards($this->user);

            if ($this->template->type === GiftCardTemplate::TYPE_MYSTERY) {
                $this->code->setActualRewards($actualRewards);
            }

            $this->giveRewards($actualRewards);

            $inviteRewards = null;
            if ($this->user->invite_user_id && isset($actualRewards['invite_reward_rate'])) {
                $inviteRewards = $this->giveInviteRewards($actualRewards);
            }

            $this->code->markAsUsed($this->user);

            GiftCardUsage::createRecord(
                $this->code,
                $this->user,
                $actualRewards,
                array_merge($options, [
                    'invite_rewards' => $inviteRewards,
                    'multiplier' => $this->calculateMultiplier(),
                ])
            );

            return [
                'rewards' => $actualRewards,
                'invite_rewards' => $inviteRewards,
                'code' => $this->code->code,
                'template_name' => $this->template->name,
            ];
        });
    }

    /**
     * Distribute rewards
     */
    protected function giveRewards(array $rewards): void
    {
        $userService = app(UserService::class);

        if (isset($rewards['balance']) && $rewards['balance'] > 0) {
            if (!$userService->addBalance($this->user->id, $rewards['balance'])) {
                throw new ApiException('Failed to distribute balance');
            }
        }

        if (isset($rewards['transfer_enable']) && $rewards['transfer_enable'] > 0) {
            $this->user->transfer_enable = ($this->user->transfer_enable ?? 0) + $rewards['transfer_enable'];
        }

        if (isset($rewards['device_limit']) && $rewards['device_limit'] > 0) {
            $this->user->device_limit = ($this->user->device_limit ?? 0) + $rewards['device_limit'];
        }

        if (isset($rewards['reset_package']) && $rewards['reset_package']) {
            if ($this->user->plan_id) {
                app(TrafficResetService::class)->performReset($this->user, TrafficResetLog::SOURCE_GIFT_CARD);
            }
        }

        if (isset($rewards['plan_id'])) {
            $plan = Plan::find($rewards['plan_id']);
            if ($plan) {
                $userService->assignPlan(
                    $this->user,
                    $plan,
                    $rewards['plan_validity_days'] ?? null
                );
            }
        } else {
            // Only process independent validity rewards when it's not a plan card
            if (isset($rewards['expire_days']) && $rewards['expire_days'] > 0) {
                $userService->extendSubscription($this->user, $rewards['expire_days']);
            }
        }

        // Save user changes
        if (!$this->user->save()) {
            throw new ApiException('Failed to update user information');
        }
    }

    /**
     * Distribute inviter rewards
     */
    protected function giveInviteRewards(array $rewards): ?array
    {
        if (!$this->user->invite_user_id) {
            return null;
        }

        $inviteUser = User::find($this->user->invite_user_id);
        if (!$inviteUser) {
            return null;
        }

        $rate = $rewards['invite_reward_rate'] ?? 0.2;
        $inviteRewards = [];

        $userService = app(UserService::class);

        // Inviter balance reward
        if (isset($rewards['balance']) && $rewards['balance'] > 0) {
            $inviteBalance = intval($rewards['balance'] * $rate);
            if ($inviteBalance > 0) {
                $userService->addBalance($inviteUser->id, $inviteBalance);
                $inviteRewards['balance'] = $inviteBalance;
            }
        }

        // Inviter traffic reward
        if (isset($rewards['transfer_enable']) && $rewards['transfer_enable'] > 0) {
            $inviteTransfer = intval($rewards['transfer_enable'] * $rate);
            if ($inviteTransfer > 0) {
                $inviteUser->transfer_enable = ($inviteUser->transfer_enable ?? 0) + $inviteTransfer;
                $inviteUser->save();
                $inviteRewards['transfer_enable'] = $inviteTransfer;
            }
        }

        return $inviteRewards;
    }

    /**
     * Calculate multiplier
     */
    protected function calculateMultiplier(): float
    {
        return $this->getFestivalBonus();
    }

    /**
     * Get festival bonus multiplier
     */
    private function getFestivalBonus(): float
    {
        $festivalConfig = $this->template->special_config ?? [];
        $now = time();

        if (
            isset($festivalConfig['start_time'], $festivalConfig['end_time']) &&
            $now >= $festivalConfig['start_time'] &&
            $now <= $festivalConfig['end_time']
        ) {
            return $festivalConfig['festival_bonus'] ?? 1.0;
        }

        return 1.0;
    }

    /**
     * Get redemption code information (excluding sensitive information)
     */
    public function getCodeInfo(): array
    {
        $info = [
            'code' => $this->code->code,
            'template' => [
                'name' => $this->template->name,
                'description' => $this->template->description,
                'type' => $this->template->type,
                'type_name' => $this->template->type_name,
                'icon' => $this->template->icon,
                'background_image' => $this->template->background_image,
                'theme_color' => $this->template->theme_color,
            ],
            'status' => $this->code->status,
            'status_name' => $this->code->status_name,
            'expires_at' => $this->code->expires_at,
            'usage_count' => $this->code->usage_count,
            'max_usage' => $this->code->max_usage,
        ];
        if ($this->template->type === GiftCardTemplate::TYPE_PLAN) {
            $plan = Plan::find($this->code->template->rewards['plan_id']);
            if ($plan) {
                $info['plan_info'] = PlanResource::make($plan)->toArray(request());
            }
        }
        return $info;
    }

    /**
     * Preview rewards (without actual distribution)
     */
    public function previewRewards(): array
    {
        if (!$this->user) {
            throw new ApiException('User not set');
        }

        return $this->template->calculateActualRewards($this->user);
    }

    /**
     * Get redemption code
     */
    public function getCode(): GiftCardCode
    {
        return $this->code;
    }

    /**
     * Get template
     */
    public function getTemplate(): GiftCardTemplate
    {
        return $this->template;
    }

    /**
     * Log usage
     */
    protected function logUsage(string $action, array $data = []): void
    {
        Log::info('Gift card usage log', [
            'action' => $action,
            'code' => $this->code->code,
            'template_id' => $this->template->id,
            'user_id' => $this->user?->id,
            'data' => $data,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
