<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Old price field to new period mapping
     */
    private const PERIOD_MAPPING = [
        'month_price' => 'monthly',
        'quarter_price' => 'quarterly',
        'half_year_price' => 'half_yearly',
        'year_price' => 'yearly',
        'two_year_price' => 'two_yearly',
        'three_year_price' => 'three_yearly',
        'onetime_price' => 'onetime',
        'reset_price' => 'reset_traffic'
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Batch update order period fields
        foreach (self::PERIOD_MAPPING as $oldPeriod => $newPeriod) {
            DB::table('v2_order')
                ->where('period', $oldPeriod)
                ->update(['period' => $newPeriod]);
        }

        // Check if there are still unconverted records
        $unconvertedCount = DB::table('v2_order')
            ->whereNotIn('period', array_values(self::PERIOD_MAPPING))
            ->count();

        if ($unconvertedCount > 0) {
            Log::warning("Found {$unconvertedCount} orders with unconverted period values");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback operation - convert new period values back to old price field names
        foreach (self::PERIOD_MAPPING as $oldPeriod => $newPeriod) {
            DB::table('v2_order')
                ->where('period', $newPeriod)
                ->update(['period' => $oldPeriod]);
        }
    }
};