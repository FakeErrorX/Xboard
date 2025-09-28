<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTrafficResetLogsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('v2_traffic_reset_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->comment('User ID');
            $table->string('reset_type', 50)->comment('Reset type');
            $table->timestamp('reset_time')->comment('Reset time');
            $table->bigInteger('old_upload')->default(0)->comment('Upload traffic before reset');
            $table->bigInteger('old_download')->default(0)->comment('Download traffic before reset');
            $table->bigInteger('old_total')->default(0)->comment('Total traffic before reset');
            $table->bigInteger('new_upload')->default(0)->comment('Upload traffic after reset');
            $table->bigInteger('new_download')->default(0)->comment('Download traffic after reset');
            $table->bigInteger('new_total')->default(0)->comment('Total traffic after reset');
            $table->string('trigger_source', 50)->comment('Trigger source');
            $table->json('metadata')->nullable()->comment('Additional metadata');
            $table->timestamps();
            
            // Add indexes
            $table->index('user_id', 'idx_user_id');
            $table->index('reset_time', 'idx_reset_time');
            $table->index(['user_id', 'reset_time'], 'idx_user_reset_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v2_traffic_reset_logs');
    }
} 