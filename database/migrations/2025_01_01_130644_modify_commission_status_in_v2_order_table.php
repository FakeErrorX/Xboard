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
        Schema::table('v2_order', function (Blueprint $table) {
            $table->integer('commission_status')->nullable()->default(null)->comment('0=pending, 1=processing, 2=valid, 3=invalid')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('v2_order', function (Blueprint $table) {
            $table->integer('commission_status')->default(false)->comment('0=pending, 1=processing, 2=valid, 3=invalid')->change();
        });
    }
};
