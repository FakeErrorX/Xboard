<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class AddTrafficResetFieldsToUsers extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        ini_set('memory_limit', '-1');
        if (!Schema::hasColumn('v2_user', 'next_reset_at')) {
            Schema::table('v2_user', function (Blueprint $table) {
                $table->integer('next_reset_at')->nullable()->after('expired_at')->comment('Next traffic reset time');
                $table->integer('last_reset_at')->nullable()->after('next_reset_at')->comment('Last traffic reset time');
                $table->integer('reset_count')->default(0)->after('last_reset_at')->comment('Traffic reset count');
                $table->index('next_reset_at', 'idx_next_reset_at');
            });
        }

        // Set initial reset time for existing users
        Artisan::call('reset:traffic', ['--fix-null' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('v2_user', function (Blueprint $table) {
            $table->dropIndex('idx_next_reset_at');
            $table->dropColumn(['next_reset_at', 'last_reset_at', 'reset_count']);
        });
    }
}