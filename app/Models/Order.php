<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Order
 *
 * @property int $id
 * @property int $user_id
 * @property int $plan_id
 * @property int|null $payment_id
 * @property string $period
 * @property string $trade_no
 * @property int $total_amount
 * @property int|null $handling_amount
 * @property int|null $balance_amount
 * @property int|null $refund_amount
 * @property int|null $surplus_amount
 * @property int $type
 * @property int $status
 * @property array|null $surplus_order_ids
 * @property int|null $coupon_id
 * @property int $created_at
 * @property int $updated_at
 * @property int|null $commission_status
 * @property int|null $invite_user_id
 * @property int|null $actual_commission_balance
 * @property int|null $commission_rate
 * @property int|null $commission_auto_check
 * @property int|null $commission_balance
 * @property int|null $discount_amount
 * @property int|null $paid_at
 * @property string|null $callback_no
 *
 * @property-read Plan $plan
 * @property-read Payment|null $payment
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CommissionLog> $commission_log
 */
class Order extends Model
{
    protected $table = 'v2_order';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'surplus_order_ids' => 'array',
        'handling_amount' => 'integer'
    ];

    const STATUS_PENDING = 0; // Pending payment
    const STATUS_PROCESSING = 1; // Processing
    const STATUS_CANCELLED = 2; // Cancelled
    const STATUS_COMPLETED = 3; // Completed
    const STATUS_DISCOUNTED = 4; // Discounted

    public static $statusMap = [
        self::STATUS_PENDING => 'Pending Payment',
        self::STATUS_PROCESSING => 'Processing',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_DISCOUNTED => 'Discounted',
    ];

    const TYPE_NEW_PURCHASE = 1; // New purchase
    const TYPE_RENEWAL = 2; // Renewal
    const TYPE_UPGRADE = 3; // Upgrade
    const TYPE_RESET_TRAFFIC = 4; // Traffic reset package
    public static $typeMap = [
        self::TYPE_NEW_PURCHASE => 'New Purchase',
        self::TYPE_RENEWAL => 'Renewal',
        self::TYPE_UPGRADE => 'Upgrade',
        self::TYPE_RESET_TRAFFIC => 'Traffic Reset',
    ];

    /**
     * Get payment method associated with order
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'id');
    }

    /**
     * Get user associated with order
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Get inviter
     */
    public function invite_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invite_user_id', 'id');
    }

    /**
     * Get plan associated with order
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

    /**
     * Get commission records associated with order
     */
    public function commission_log(): HasMany
    {
        return $this->hasMany(CommissionLog::class, 'trade_no', 'trade_no');
    }
}
