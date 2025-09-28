<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Gift card template table
        Schema::create('v2_gift_card_template', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Gift card name');
            $table->text('description')->nullable()->comment('Gift card description');
            $table->tinyInteger('type')->comment('Card type: 1 balance, 2 validity, 3 traffic, 4 reset package, 5 plan, 6 combo, 7 mystery box, 8 task, 9 level, 10 festival');
            $table->tinyInteger('status')->default(1)->comment('Status: 0 disabled, 1 enabled');
            $table->json('conditions')->nullable()->comment('Usage conditions configuration');
            $table->json('rewards')->comment('Rewards configuration');
            $table->json('limits')->nullable()->comment('Limitation conditions');
            $table->json('special_config')->nullable()->comment('Special configuration (festival time, level multiplier, etc.)');
            $table->string('icon')->nullable()->comment('Card icon');
            $table->string('background_image')->nullable()->comment('Background image URL');
            $table->string('theme_color', 7)->default('#1890ff')->comment('Theme color');
            $table->integer('sort')->default(0)->comment('Sort order');
            $table->integer('admin_id')->comment('Creator admin ID');
            $table->integer('created_at');
            $table->integer('updated_at');

            $table->index(['type', 'status'], 'idx_gift_template_type_status');
            $table->index('created_at', 'idx_gift_template_created_at');
        });

        // Gift card redemption code table
        Schema::create('v2_gift_card_code', function (Blueprint $table) {
            $table->id();
            $table->integer('template_id')->comment('Template ID');
            $table->string('code', 32)->unique()->comment('Redemption code');
            $table->string('batch_id', 32)->nullable()->comment('Batch ID');
            $table->tinyInteger('status')->default(0)->comment('Status: 0 unused, 1 used, 2 expired, 3 disabled');
            $table->integer('user_id')->nullable()->comment('User ID who used it');
            $table->integer('used_at')->nullable()->comment('Used time');
            $table->integer('expires_at')->nullable()->comment('Expiration time');
            $table->json('actual_rewards')->nullable()->comment('Actual rewards received (for mystery box, etc.)');
            $table->integer('usage_count')->default(0)->comment('Usage count (share card)');
            $table->integer('max_usage')->default(1)->comment('Maximum usage count');
            $table->json('metadata')->nullable()->comment('Additional data');
            $table->integer('created_at');
            $table->integer('updated_at');

            $table->index('template_id', 'idx_gift_code_template_id');
            $table->index('status', 'idx_gift_code_status');
            $table->index('user_id', 'idx_gift_code_user_id');
            $table->index('batch_id', 'idx_gift_code_batch_id');
            $table->index('expires_at', 'idx_gift_code_expires_at');
            $table->index(['code', 'status', 'expires_at'], 'idx_gift_code_lookup');
        });

        // Gift card usage log table
        Schema::create('v2_gift_card_usage', function (Blueprint $table) {
            $table->id();
            $table->integer('code_id')->comment('Redemption code ID');
            $table->integer('template_id')->comment('Template ID');
            $table->integer('user_id')->comment('User ID who used it');
            $table->integer('invite_user_id')->nullable()->comment('Inviter user ID');
            $table->json('rewards_given')->comment('Actual rewards distributed');
            $table->json('invite_rewards')->nullable()->comment('Rewards received by inviter');
            $table->integer('user_level_at_use')->nullable()->comment('User level at time of use');
            $table->integer('plan_id_at_use')->nullable()->comment('User plan ID at time of use');
            $table->decimal('multiplier_applied', 3, 2)->default(1.00)->comment('Applied multiplier');
            $table->string('ip_address', 45)->nullable()->comment('IP address used');
            $table->text('user_agent')->nullable()->comment('User agent');
            $table->text('notes')->nullable()->comment('Notes');
            $table->integer('created_at');

            $table->index('code_id', 'idx_gift_usage_code_id');
            $table->index('template_id', 'idx_gift_usage_template_id');
            $table->index('user_id', 'idx_gift_usage_user_id');
            $table->index('invite_user_id', 'idx_gift_usage_invite_user_id');
            $table->index('created_at', 'idx_gift_usage_created_at');
            $table->index(['user_id', 'created_at'], 'idx_gift_usage_user_usage');
            $table->index(['template_id', 'created_at'], 'idx_gift_usage_template_stats');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v2_gift_card_usage');
        Schema::dropIfExists('v2_gift_card_code');
        Schema::dropIfExists('v2_gift_card_template');
    }
};
