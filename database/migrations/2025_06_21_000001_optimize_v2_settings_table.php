<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class OptimizeV2SettingsTable extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::table('v2_settings', function (Blueprint $table) {
      // Change value field to MEDIUMTEXT, supporting up to 16MB content
      $table->mediumText('value')->nullable()->change();
      // Add optimization indexes
      $table->index('name', 'idx_setting_name');
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::table('v2_settings', function (Blueprint $table) {
      $table->string('value')->nullable()->change();
      $table->dropIndex('idx_setting_name');
    });
  }
}