<?php

namespace App\Models;

use App\Utils\Helper;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\User
 *
 * @property int $id User ID
 * @property string $email Email
 * @property string $password Password
 * @property string|null $password_algo Encryption method
 * @property string|null $password_salt Encryption salt
 * @property string $token Invite token
 * @property string $uuid
 * @property int|null $invite_user_id Inviter
 * @property int|null $plan_id Subscription ID
 * @property int|null $group_id Permission group ID
 * @property int|null $transfer_enable Traffic quota (KB)
 * @property int|null $speed_limit Speed limit Mbps
 * @property int|null $u Upload traffic
 * @property int|null $d Download traffic
 * @property int|null $banned Whether banned
 * @property int|null $remind_expire Expiration reminder
 * @property int|null $remind_traffic Traffic reminder
 * @property int|null $expired_at Expiration time
 * @property int|null $balance Balance
 * @property int|null $commission_balance Commission balance
 * @property float $commission_rate Commission rate
 * @property int|null $commission_type Commission type
 * @property int|null $device_limit Device limit count
 * @property int|null $discount Discount
 * @property int|null $last_login_at Last login time
 * @property int|null $parent_id Parent account ID
 * @property int|null $is_admin Whether admin
 * @property int|null $next_reset_at Next traffic reset time
 * @property int|null $last_reset_at Last traffic reset time
 * @property int|null $telegram_id Telegram ID
 * @property int $reset_count Traffic reset count
 * @property int $created_at
 * @property int $updated_at
 * @property bool $commission_auto_check Whether to auto-calculate commission
 *
 * @property-read User|null $invite_user Inviter information
 * @property-read \App\Models\Plan|null $plan User subscription plan
 * @property-read ServerGroup|null $group Permission group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, InviteCode> $codes Invite code list
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Order> $orders Order list
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StatUser> $stat Statistics information
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Ticket> $tickets Ticket list
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TrafficResetLog> $trafficResetLogs Traffic reset records
 * @property-read User|null $parent Parent account
 * @property-read string $subscribe_url Subscription URL (dynamically generated)
 */
class User extends Authenticatable
{
    use HasApiTokens;
    protected $table = 'v2_user';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'banned' => 'boolean',
        'is_admin' => 'boolean',
        'is_staff' => 'boolean',
        'remind_expire' => 'boolean',
        'remind_traffic' => 'boolean',
        'commission_auto_check' => 'boolean',
        'commission_rate' => 'float',
        'next_reset_at' => 'timestamp',
        'last_reset_at' => 'timestamp',
    ];
    protected $hidden = ['password'];

    public const COMMISSION_TYPE_SYSTEM = 0;
    public const COMMISSION_TYPE_PERIOD = 1;
    public const COMMISSION_TYPE_ONETIME = 2;

    // Get inviter information
    public function invite_user(): BelongsTo
    {
        return $this->belongsTo(self::class, 'invite_user_id', 'id');
    }

    /**
     * Get user subscription plan
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ServerGroup::class, 'group_id', 'id');
    }

    // Get user invite code list
    public function codes(): HasMany
    {
        return $this->hasMany(InviteCode::class, 'user_id', 'id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_id', 'id');
    }

    public function stat(): HasMany
    {
        return $this->hasMany(StatUser::class, 'user_id', 'id');
    }

    // Associated ticket list
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'user_id', 'id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    /**
     * Associated traffic reset records
     */
    public function trafficResetLogs(): HasMany
    {
        return $this->hasMany(TrafficResetLog::class, 'user_id', 'id');
    }

    /**
     * Check if user is in active status
     */
    public function isActive(): bool
    {
        return !$this->banned && 
               ($this->expired_at === null || $this->expired_at > time()) &&
               $this->plan_id !== null;
    }

    /**
     * Check if traffic needs to be reset
     */
    public function shouldResetTraffic(): bool
    {
        return $this->isActive() &&
               $this->next_reset_at !== null &&
               $this->next_reset_at <= time();
    }

    /**
     * Get total used traffic
     */
    public function getTotalUsedTraffic(): int
    {
        return ($this->u ?? 0) + ($this->d ?? 0);
    }

    /**
     * Get remaining traffic
     */
    public function getRemainingTraffic(): int
    {
        $used = $this->getTotalUsedTraffic();
        $total = $this->transfer_enable ?? 0;
        return max(0, $total - $used);
    }

    /**
     * Get traffic usage percentage
     */
    public function getTrafficUsagePercentage(): float
    {
        $total = $this->transfer_enable ?? 0;
        if ($total <= 0) {
            return 0;
        }
        
        $used = $this->getTotalUsedTraffic();
        return min(100, ($used / $total) * 100);
    }
}
