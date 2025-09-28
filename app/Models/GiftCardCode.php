<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\GiftCardCode
 *
 * @property int $id
 * @property int $template_id Template ID
 * @property GiftCardTemplate $template Associated template
 * @property string $code Redemption code
 * @property string|null $batch_id Batch ID
 * @property int $status Status
 * @property int|null $user_id User ID who used it
 * @property int|null $used_at Usage time
 * @property int|null $expires_at Expiry time
 * @property array|null $actual_rewards Actual rewards
 * @property int $usage_count Usage count
 * @property int $max_usage Maximum usage count
 * @property array|null $metadata Additional data
 * @property int $created_at
 * @property int $updated_at
 */
class GiftCardCode extends Model
{
    protected $table = 'v2_gift_card_code';
    protected $dateFormat = 'U';

    // Status constants
    const STATUS_UNUSED = 0;        // Unused
    const STATUS_USED = 1;          // Used
    const STATUS_EXPIRED = 2;       // Expired
    const STATUS_DISABLED = 3;      // Disabled

    protected $fillable = [
        'template_id',
        'code',
        'batch_id',
        'status',
        'user_id',
        'used_at',
        'expires_at',
        'actual_rewards',
        'usage_count',
        'max_usage',
        'metadata'
    ];

    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'used_at' => 'timestamp',
        'expires_at' => 'timestamp',
        'actual_rewards' => 'array',
        'metadata' => 'array'
    ];

    /**
     * Get status mapping
     */
    public static function getStatusMap(): array
    {
        return [
            self::STATUS_UNUSED => 'Unused',
            self::STATUS_USED => 'Used',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_DISABLED => 'Disabled',
        ];
    }

    /**
     * Get status name
     */
    public function getStatusNameAttribute(): string
    {
        return self::getStatusMap()[$this->status] ?? 'Unknown status';
    }

    /**
     * Associated gift card template
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(GiftCardTemplate::class, 'template_id');
    }

    /**
     * Associated user who used it
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Associated usage records
     */
    public function usages(): HasMany
    {
        return $this->hasMany(GiftCardUsage::class, 'code_id');
    }

    /**
     * Check if available
     */
    public function isAvailable(): bool
    {
        // Check status
        if (in_array($this->status, [self::STATUS_EXPIRED, self::STATUS_DISABLED])) {
            return false;
        }

        // Check if expired
        if ($this->expires_at && $this->expires_at < time()) {
            return false;
        }

        // Check usage count
        if ($this->usage_count >= $this->max_usage) {
            return false;
        }

        return true;
    }

    /**
     * Check if the gift card code has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at < time();
    }

    /**
     * Mark as used
     */
    public function markAsUsed(User $user): bool
    {
        $this->status = self::STATUS_USED;
        $this->user_id = $user->id;
        $this->used_at = time();
        $this->usage_count += 1;

        return $this->save();
    }

    /**
     * Mark as expired
     */
    public function markAsExpired(): bool
    {
        $this->status = self::STATUS_EXPIRED;
        return $this->save();
    }

    /**
     * Mark as disabled
     */
    public function markAsDisabled(): bool
    {
        $this->status = self::STATUS_DISABLED;
        return $this->save();
    }

    /**
     * Generate gift card code
     */
    public static function generateCode(string $prefix = 'GC'): string
    {
        do {
            $safePrefix = (string) $prefix;
            $code = $safePrefix . strtoupper(substr(md5(uniqid($safePrefix . mt_rand(), true)), 0, 12));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Batch generate gift card codes
     */
    public static function batchGenerate(int $templateId, int $count, array $options = []): string
    {
        $batchId = uniqid('batch_');
        $prefix = $options['prefix'] ?? 'GC';
        $expiresAt = $options['expires_at'] ?? null;
        $maxUsage = $options['max_usage'] ?? 1;

        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = [
                'template_id' => $templateId,
                'code' => self::generateCode($prefix),
                'batch_id' => $batchId,
                'status' => self::STATUS_UNUSED,
                'expires_at' => $expiresAt,
                'max_usage' => $maxUsage,
                'created_at' => time(),
                'updated_at' => time(),
            ];
        }

        self::insert($codes);

        return $batchId;
    }

    /**
     * Set actual rewards (for blind box, etc.)
     */
    public function setActualRewards(array $rewards): bool
    {
        $this->actual_rewards = $rewards;
        return $this->save();
    }

    /**
     * Get actual rewards
     */
    public function getActualRewards(): array
    {
        return $this->actual_rewards ?? $this->template->rewards ?? [];
    }

    /**
     * Check gift card code format
     */
    public static function validateCodeFormat(string $code): bool
    {
        // Basic format validation: alphanumeric combination, length 8-32
        return preg_match('/^[A-Z0-9]{8,32}$/', $code);
    }

    /**
     * Get gift card codes by batch ID
     */
    public static function getByBatchId(string $batchId)
    {
        return self::where('batch_id', $batchId)->get();
    }

    /**
     * Clean up expired gift card codes
     */
    public static function cleanupExpired(): int
    {
        $count = self::where('status', self::STATUS_UNUSED)
            ->where('expires_at', '<', time())
            ->count();

        self::where('status', self::STATUS_UNUSED)
            ->where('expires_at', '<', time())
            ->update(['status' => self::STATUS_EXPIRED]);

        return $count;
    }
}