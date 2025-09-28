<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * App\Models\Plan
 *
 * @property int $id
 * @property string $name Plan name
 * @property int|null $group_id Permission group ID
 * @property int $transfer_enable Traffic limit (KB)
 * @property int|null $speed_limit Speed limit Mbps
 * @property bool $show Whether to show
 * @property bool $renew Whether to allow renewal
 * @property bool $sell Whether to allow purchase
 * @property array|null $prices Price configuration
 * @property array|null $tags Tags
 * @property int $sort Sort order
 * @property string|null $content Plan description
 * @property int|null $reset_traffic_method Traffic reset method
 * @property int|null $capacity_limit Subscription capacity limit
 * @property int|null $device_limit Device limit
 * @property int $created_at
 * @property int $updated_at
 * 
 * @property-read ServerGroup|null $group Associated permission group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Order> $order Associated orders
 */
class Plan extends Model
{
    use HasFactory;

    protected $table = 'v2_plan';
    protected $dateFormat = 'U';

    // Define traffic reset methods
    public const RESET_TRAFFIC_FOLLOW_SYSTEM = null;    // Follow system settings
    public const RESET_TRAFFIC_FIRST_DAY_MONTH = 0;  // 1st of each month
    public const RESET_TRAFFIC_MONTHLY = 1;          // Monthly reset
    public const RESET_TRAFFIC_NEVER = 2;            // No reset
    public const RESET_TRAFFIC_FIRST_DAY_YEAR = 3;   // January 1st each year
    public const RESET_TRAFFIC_YEARLY = 4;           // Yearly reset

    // Define price types
    public const PRICE_TYPE_RESET_TRAFFIC = 'reset_traffic';  // Reset traffic price

    // Define available subscription periods
    public const PERIOD_MONTHLY = 'monthly';
    public const PERIOD_QUARTERLY = 'quarterly';
    public const PERIOD_HALF_YEARLY = 'half_yearly';
    public const PERIOD_YEARLY = 'yearly';
    public const PERIOD_TWO_YEARLY = 'two_yearly';
    public const PERIOD_THREE_YEARLY = 'three_yearly';
    public const PERIOD_ONETIME = 'onetime';
    public const PERIOD_RESET_TRAFFIC = 'reset_traffic';

    // Define legacy period mapping
    public const LEGACY_PERIOD_MAPPING = [
        'month_price' => self::PERIOD_MONTHLY,
        'quarter_price' => self::PERIOD_QUARTERLY,
        'half_year_price' => self::PERIOD_HALF_YEARLY,
        'year_price' => self::PERIOD_YEARLY,
        'two_year_price' => self::PERIOD_TWO_YEARLY,
        'three_year_price' => self::PERIOD_THREE_YEARLY,
        'onetime_price' => self::PERIOD_ONETIME,
        'reset_price' => self::PERIOD_RESET_TRAFFIC
    ];

    protected $fillable = [
        'group_id',
        'transfer_enable',
        'name',
        'speed_limit',
        'show',
        'sort',
        'renew',
        'content',
        'prices',
        'reset_traffic_method',
        'capacity_limit',
        'sell',
        'device_limit',
        'tags'
    ];

    protected $casts = [
        'show' => 'boolean',
        'renew' => 'boolean',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'group_id' => 'integer',
        'prices' => 'array',
        'tags' => 'array',
        'reset_traffic_method' => 'integer',
    ];

    /**
     * Get all available traffic reset methods
     *
     * @return array
     */
    public static function getResetTrafficMethods(): array
    {
        return [
            self::RESET_TRAFFIC_FOLLOW_SYSTEM => 'Follow system settings',
            self::RESET_TRAFFIC_FIRST_DAY_MONTH => '1st of each month',
            self::RESET_TRAFFIC_MONTHLY => 'Monthly reset',
            self::RESET_TRAFFIC_NEVER => 'No reset',
            self::RESET_TRAFFIC_FIRST_DAY_YEAR => 'January 1st each year',
            self::RESET_TRAFFIC_YEARLY => 'Yearly reset',
        ];
    }

    /**
     * Get all available subscription periods
     *
     * @return array
     */
    public static function getAvailablePeriods(): array
    {
        return [
            self::PERIOD_MONTHLY => [
                'name' => 'Monthly',
                'days' => 30,
                'value' => 1
            ],
            self::PERIOD_QUARTERLY => [
                'name' => 'Quarterly',
                'days' => 90,
                'value' => 3
            ],
            self::PERIOD_HALF_YEARLY => [
                'name' => 'Half-yearly',
                'days' => 180,
                'value' => 6
            ],
            self::PERIOD_YEARLY => [
                'name' => 'Yearly',
                'days' => 365,
                'value' => 12
            ],
            self::PERIOD_TWO_YEARLY => [
                'name' => 'Two years',
                'days' => 730,
                'value' => 24
            ],
            self::PERIOD_THREE_YEARLY => [
                'name' => 'Three years',
                'days' => 1095,
                'value' => 36
            ],
            self::PERIOD_ONETIME => [
                'name' => 'One-time',
                'days' => -1,
                'value' => -1
            ],
            self::PERIOD_RESET_TRAFFIC => [
                'name' => 'Reset traffic',
                'days' => -1,
                'value' => -1
            ],
        ];
    }

    /**
     * Get price for specific period
     *
     * @param string $period
     * @return int|null
     */
    public function getPriceByPeriod(string $period): ?int
    {
        return $this->prices[$period] ?? null;
    }

    /**
     * Get all periods with set prices
     *
     * @return array
     */
    public function getActivePeriods(): array
    {
        return array_filter(
            self::getAvailablePeriods(),
            fn($period) => isset($this->prices[$period])
            && $this->prices[$period] > 0,
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Set price for specific period
     *
     * @param string $period
     * @param int $price
     * @return void
     * @throws InvalidArgumentException
     */
    public function setPeriodPrice(string $period, int $price): void
    {
        if (!array_key_exists($period, self::getAvailablePeriods())) {
            throw new InvalidArgumentException("Invalid period: {$period}");
        }

        $prices = $this->prices ?? [];
        $prices[$period] = $price;
        $this->prices = $prices;
    }

    /**
     * Remove price for specific period
     *
     * @param string $period
     * @return void
     */
    public function removePeriodPrice(string $period): void
    {
        $prices = $this->prices ?? [];
        unset($prices[$period]);
        $this->prices = $prices;
    }

    /**
     * Get all prices with their corresponding period information
     *
     * @return array
     */
    public function getPriceList(): array
    {
        $prices = $this->prices ?? [];
        $periods = self::getAvailablePeriods();

        $priceList = [];
        foreach ($prices as $period => $price) {
            if (isset($periods[$period]) && $price > 0) {
                $priceList[$period] = [
                    'period' => $periods[$period],
                    'price' => $price,
                    'average_price' => $periods[$period]['value'] > 0
                        ? round($price / $periods[$period]['value'], 2)
                        : $price
                ];
            }
        }

        return $priceList;
    }

    /**
     * Check if traffic can be reset
     *
     * @return bool
     */
    public function canResetTraffic(): bool
    {
        return $this->reset_traffic_method !== self::RESET_TRAFFIC_NEVER
            && $this->getResetTrafficPrice() > 0;
    }

    /**
     * Get reset traffic price
     *
     * @return int
     */
    public function getResetTrafficPrice(): int
    {
        return $this->prices[self::PRICE_TYPE_RESET_TRAFFIC] ?? 0;
    }

    /**
     * Calculate effective days for specific period
     *
     * @param string $period
     * @return int -1 means permanently valid
     * @throws InvalidArgumentException
     */
    public static function getPeriodDays(string $period): int
    {
        $periods = self::getAvailablePeriods();
        if (!isset($periods[$period])) {
            throw new InvalidArgumentException("Invalid period: {$period}");
        }

        return $periods[$period]['days'];
    }

    /**
     * Check if period is valid
     *
     * @param string $period
     * @return bool
     */
    public static function isValidPeriod(string $period): bool
    {
        return array_key_exists($period, self::getAvailablePeriods());
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function group(): HasOne
    {
        return $this->hasOne(ServerGroup::class, 'id', 'group_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Set traffic reset method
     *
     * @param int $method
     * @return void
     * @throws InvalidArgumentException
     */
    public function setResetTrafficMethod(int $method): void
    {
        if (!array_key_exists($method, self::getResetTrafficMethods())) {
            throw new InvalidArgumentException("Invalid reset traffic method: {$method}");
        }

        $this->reset_traffic_method = $method;
    }

    /**
     * Set reset traffic price
     *
     * @param int $price
     * @return void
     */
    public function setResetTrafficPrice(int $price): void
    {
        $prices = $this->prices ?? [];
        $prices[self::PRICE_TYPE_RESET_TRAFFIC] = max(0, $price);
        $this->prices = $prices;
    }

    public function order(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}