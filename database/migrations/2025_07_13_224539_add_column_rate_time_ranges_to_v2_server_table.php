<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('v2_server', function (Blueprint $table) {
            $table->boolean('rate_time_enable')->default(false)->comment('Enable dynamic rate')->after('rate');
            $table->json('rate_time_ranges')->nullable()->comment('Dynamic rate rules')->after('rate_time_enable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('v2_server', function (Blueprint $table) {
            $table->dropColumn('rate_time_enable');
            $table->dropColumn('rate_time_ranges');
        });
    }
};
