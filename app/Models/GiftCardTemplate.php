<?php

namespace App\Models;

use Dflydev\DotAccessData\Data;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\GiftCardTemplate
 *
 * @property int $id
 * @property string $name Gift card name
 * @property string|null $description Gift card description  
 * @property int $type Card type
 * @property boolean $status Status
 * @property array|null $conditions Usage condition configuration
 * @property array $rewards Reward configuration
 * @property array|null $limits Limit conditions
 * @property array|null $special_config Special configuration
 * @property string|null $icon Card icon
 * @property string $theme_color Theme color
 * @property int $sort Sort order
 * @property int $admin_id Creator admin ID
 * @property int $created_at
 * @property int $updated_at
 */
class GiftCardTemplate extends Model
{
    protected $table = 'v2_gift_card_template';
    protected $dateFormat = 'U';

    // Card type constants
    const TYPE_GENERAL = 1;         // General gift card
    const TYPE_PLAN = 2;            // Plan gift card
    const TYPE_MYSTERY = 3;         // Mystery box gift card

    protected $fillable = [
        'name',
        'description',
        'type',
        'status',
        'conditions',
        'rewards',
        'limits',
        'special_config',
        'icon',
        'background_image',
        'theme_color',
        'sort',
        'admin_id'
    ];

    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'conditions' => 'array',
        'rewards' => 'array',
        'limits' => 'array',
        'special_config' => 'array',
        'status' => 'boolean'
    ];

    /**
     * Get card type mapping
     */
    public static function getTypeMap(): array
    {
        return [
            self::TYPE_GENERAL => 'General Gift Card',
            self::TYPE_PLAN => 'Plan Gift Card',
            self::TYPE_MYSTERY => 'Mystery Box Gift Card',
        ];
    }

    /**
     * Get type name
     */
    public function getTypeNameAttribute(): string
    {
        return self::getTypeMap()[$this->type] ?? 'Unknown Type';
    }

    /**
     * Associated redemption codes
     */
    public function codes(): HasMany
    {
        return $this->hasMany(GiftCardCode::class, 'template_id');
    }

    /**
     * Associated usage records
     */
    public function usages(): HasMany
    {
        return $this->hasMany(GiftCardUsage::class, 'template_id');
    }

    /**
     * Associated statistics data
     */
    public function stats(): HasMany
    {
        return $this->hasMany(GiftCardUsage::class, 'template_id');
    }

    /**
     * Check if available
     */
    public function isAvailable(): bool
    {
        return $this->status;
    }

    /**
     * Check if user meets usage conditions
     */
    public function checkUserConditions(User $user): bool
    {
        switch ($this->type) {
            case self::TYPE_GENERAL:
                $rewards = $this->rewards ?? [];
                if (isset($rewards['transfer_enable']) || isset($rewards['expire_days']) || isset($rewards['reset_package'])) {
                    if (!$user->plan_id) {
                        return false;
                    }
                }
                break;
            case self::TYPE_PLAN:
                if ($user->isActive()) {
                    return false;
                }
                break;
        }

        $conditions = $this->conditions ?? [];

        // Check new user condition
        if (isset($conditions['new_user_only']) && $conditions['new_user_only']) {
            $maxDays = $conditions['new_user_max_days'] ?? 7;
            if ($user->created_at < (time() - ($maxDays * 86400))) {
                return false;
            }
        }

        // Check paid user condition
        if (isset($conditions['paid_user_only']) && $conditions['paid_user_only']) {
            $paidOrderExists = $user->orders()->where('status', Order::STATUS_COMPLETED)->exists();
            if (!$paidOrderExists) {
                return false;
            }
        }

        // Check allowed plans
        if (isset($conditions['allowed_plans']) && $user->plan_id) {
            if (!in_array($user->plan_id, $conditions['allowed_plans'])) {
                return false;
            }
        }

        // Check if inviter is required
        if (isset($conditions['require_invite']) && $conditions['require_invite']) {
            if (!$user->invite_user_id) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate actual rewards
     */
    public function calculateActualRewards(User $user): array
    {
        $baseRewards = $this->rewards;
        $actualRewards = $baseRewards;

        // Handle mystery box random rewards
        if ($this->type === self::TYPE_MYSTERY && isset($this->rewards['random_rewards'])) {
            $randomRewards = $this->rewards['random_rewards'];
            $totalWeight = array_sum(array_column($randomRewards, 'weight'));
            $random = mt_rand(1, $totalWeight);
            $currentWeight = 0;

            foreach ($randomRewards as $reward) {
                $currentWeight += $reward['weight'];
                if ($random <= $currentWeight) {
                    $actualRewards = array_merge($actualRewards, $reward);
                    unset($actualRewards['weight']);
                    break;
                }
            }
        }

        // Handle festival and other special rewards (general logic)
        if (isset($this->special_config['festival_bonus'])) {
            $now = time();
            $festivalConfig = $this->special_config;

            if (isset($festivalConfig['start_time']) && isset($festivalConfig['end_time'])) {
                if ($now >= $festivalConfig['start_time'] && $now <= $festivalConfig['end_time']) {
                    $bonus = data_get($festivalConfig, 'festival_bonus', 1.0);
                    if ($bonus > 1.0) {
                        foreach ($actualRewards as $key => &$value) {
                            if (is_numeric($value)) {
                                $value = intval($value * $bonus);
                            }
                        }
                        unset($value); // Release reference
                    }
                }
            }
        }

        return $actualRewards;
    }

    /**
     * Check usage frequency limits
     */
    public function checkUsageLimit(User $user): bool
    {
        $limits = $this->limits ?? [];

        // Check maximum usage per user
        if (isset($limits['max_use_per_user'])) {
            $usedCount = $this->usages()
                ->where('user_id', $user->id)
                ->count();
            if ($usedCount >= $limits['max_use_per_user']) {
                return false;
            }
        }

        // Check cooldown time
        if (isset($limits['cooldown_hours'])) {
            $lastUsage = $this->usages()
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastUsage && isset($lastUsage->created_at)) {
                $cooldownTime = $lastUsage->created_at + ($limits['cooldown_hours'] * 3600);
                if (time() < $cooldownTime) {
                    return false;
                }
            }
        }

        return true;
    }
}