<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\User;
use App\Exceptions\ApiException;
use Illuminate\Database\Eloquent\Collection;

class PlanService
{
    public Plan $plan;

    public function __construct(Plan $plan)
    {
        $this->plan = $plan;
    }

    /**
     * Get all available subscription plans list
     * Conditions: show and sell are true, and has sufficient capacity
     *
     * @return Collection
     */
    public function getAvailablePlans(): Collection
    {
        return Plan::where('show', true)
            ->where('sell', true)
            ->orderBy('sort')
            ->get()
            ->filter(function ($plan) {
                return $this->hasCapacity($plan);
            });
    }

    /**
     * Get availability status of specified subscription plan
     * Conditions: renew and sell are true
     *
     * @param int $planId
     * @return Plan|null
     */
    public function getAvailablePlan(int $planId): ?Plan
    {
        return Plan::where('id', $planId)
            ->where('sell', true)
            ->where('renew', true)
            ->first();
    }

    /**
     * Check if specified plan is available for specified user
     * 
     * @param Plan $plan
     * @param User $user
     * @return bool
     */
    public function isPlanAvailableForUser(Plan $plan, User $user): bool
    {
        // If it's a renewal
        if ($user->plan_id === $plan->id) {
            return $plan->renew;
        }

        // If it's a new purchase
        return $plan->show && $plan->sell && $this->hasCapacity($plan);
    }

    public function validatePurchase(User $user, string $period): void
    {
        if (!$this->plan) {
            throw new ApiException(__('Subscription plan does not exist'));
        }

        // Convert period format to new version format
        $periodKey = self::getPeriodKey($period);
        $price = $this->plan->prices[$periodKey] ?? null;

        if ($price === null) {
            throw new ApiException(__('This payment period cannot be purchased, please choose another period'));
        }

        if ($periodKey === Plan::PERIOD_RESET_TRAFFIC) {
            $this->validateResetTrafficPurchase($user);
            return;
        }

        if ($user->plan_id !== $this->plan->id && !$this->hasCapacity($this->plan)) {
            throw new ApiException(__('Current product is sold out'));
        }

        $this->validatePlanAvailability($user);
    }

    /**
     * Intelligently convert period format to new version format
     * If it's new format, return directly; if it's legacy format, convert to new format
     *
     * @param string $period
     * @return string
     */
    public static function getPeriodKey(string $period): string
    {
        // If it's new format, return directly
        if (in_array($period, self::getNewPeriods())) {
            return $period;
        }

        // If it's legacy format, convert to new format
        return Plan::LEGACY_PERIOD_MAPPING[$period] ?? $period;
    }
    /**
     * Convert period format to legacy version only
     */
    public static function convertToLegacyPeriod(string $period): string
    {
        $flippedMapping = array_flip(Plan::LEGACY_PERIOD_MAPPING);
        return $flippedMapping[$period] ?? $period;
    }

    /**
     * Get all supported new period formats
     *
     * @return array
     */
    public static function getNewPeriods(): array
    {
        return array_values(Plan::LEGACY_PERIOD_MAPPING);
    }

    /**
     * Get legacy period format
     *
     * @param string $period
     * @return string
     */
    public static function getLegacyPeriod(string $period): string
    {
        $flipped = array_flip(Plan::LEGACY_PERIOD_MAPPING);
        return $flipped[$period] ?? $period;
    }

    protected function validateResetTrafficPurchase(User $user): void
    {
        if (!app(UserService::class)->isAvailable($user) || $this->plan->id !== $user->plan_id) {
            throw new ApiException(__('Subscription has expired or no active subscription, unable to purchase Data Reset Package'));
        }
    }

    protected function validatePlanAvailability(User $user): void
    {
        if ((!$this->plan->show && !$this->plan->renew) || (!$this->plan->show && $user->plan_id !== $this->plan->id)) {
            throw new ApiException(__('This subscription has been sold out, please choose another subscription'));
        }

        if (!$this->plan->renew && $user->plan_id == $this->plan->id) {
            throw new ApiException(__('This subscription cannot be renewed, please change to another subscription'));
        }

        if (!$this->plan->show && $this->plan->renew && !app(UserService::class)->isAvailable($user)) {
            throw new ApiException(__('This subscription has expired, please change to another subscription'));
        }
    }

    public function hasCapacity(Plan $plan): bool
    {
        if ($plan->capacity_limit === null) {
            return true;
        }

        $activeUserCount = User::where('plan_id', $plan->id)
            ->where(function ($query) {
                $query->where('expired_at', '>=', time())
                    ->orWhereNull('expired_at');
            })
            ->count();

        return ($plan->capacity_limit - $activeUserCount) > 0;
    }

    public function getAvailablePeriods(Plan $plan): array
    {
        return array_filter(
            $plan->getActivePeriods(),
            fn($period) => isset($plan->prices[$period]) && $plan->prices[$period] > 0
        );
    }

    public function canResetTraffic(Plan $plan): bool
    {
        return $plan->reset_traffic_method !== Plan::RESET_TRAFFIC_NEVER
            && $plan->getResetTrafficPrice() > 0;
    }
}
