<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\GiftCardUsage
 *
 * @property int $id
 * @property int $code_id Gift card code ID
 * @property int $template_id Template ID
 * @property int $user_id User ID who used it
 * @property int|null $invite_user_id Inviter ID
 * @property array $rewards_given Actual rewards given
 * @property array|null $invite_rewards Rewards for inviter
 * @property int|null $user_level_at_use User level at use time
 * @property int|null $plan_id_at_use User plan ID at use time
 * @property float $multiplier_applied Applied multiplier
 * @property string|null $ip_address IP address used
 * @property string|null $user_agent User agent
 * @property string|null $notes Notes
 * @property int $created_at
 */
class GiftCardUsage extends Model
{
    protected $table = 'v2_gift_card_usage';
    protected $dateFormat = 'U';
    public $timestamps = false;

    protected $fillable = [
        'code_id',
        'template_id',
        'user_id',
        'invite_user_id',
        'rewards_given',
        'invite_rewards',
        'user_level_at_use',
        'plan_id_at_use',
        'multiplier_applied',
        'ip_address',
        'user_agent',
        'notes',
        'created_at'
    ];

    protected $casts = [
        'created_at' => 'timestamp',
        'rewards_given' => 'array',
        'invite_rewards' => 'array',
        'multiplier_applied' => 'float'
    ];

    /**
     * Relationship with gift card code
     */
    public function code(): BelongsTo
    {
        return $this->belongsTo(GiftCardCode::class, 'code_id');
    }

    /**
     * Relationship with template
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(GiftCardTemplate::class, 'template_id');
    }

    /**
     * Relationship with user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship with inviter
     */
    public function inviteUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invite_user_id');
    }

    /**
     * Create usage record
     */
    public static function createRecord(
        GiftCardCode $code,
        User $user,
        array $rewards,
        array $options = []
    ): self {
        return self::create([
            'code_id' => $code->id,
            'template_id' => $code->template_id,
            'user_id' => $user->id,
            'invite_user_id' => $user->invite_user_id,
            'rewards_given' => $rewards,
            'invite_rewards' => $options['invite_rewards'] ?? null,
            'user_level_at_use' => $user->plan ? $user->plan->sort : null,
            'plan_id_at_use' => $user->plan_id,
            'multiplier_applied' => $options['multiplier'] ?? 1.0,
            // 'ip_address' => $options['ip_address'] ?? null,
            'user_agent' => $options['user_agent'] ?? null,
            'notes' => $options['notes'] ?? null,
            'created_at' => time(),
        ]);
    }
} 