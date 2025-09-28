<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateV2SettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('v2_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->comment('Settings group')->nullable();
            $table->string('type')->comment('Settings type')->nullable();
            $table->string('name')->comment('Settings name')->uniqid();
            $table->string('value')->comment('Settings value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('v2_settings');
    }
}
