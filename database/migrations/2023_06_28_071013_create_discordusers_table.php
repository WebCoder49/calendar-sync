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
        Schema::create('discordusers', function (Blueprint $table) {
            $table->unsignedBigInteger('discord_id')->primary();

            $table->unsignedSmallInteger('settings_activehours_start');
            $table->unsignedSmallInteger('settings_activehours_end');
            $table->text('settings_preferences_timezone');

            $table->text('settings_calendar_selectedcalendars');

            $table->string('calauth_type', 3)->default("");
            $table->string('calauth_access_token', 2048)->default("");
            $table->string('calauth_refresh_token', 512)->default("");
            $table->unsignedInteger('calauth_expires_at')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discordusers');
    }
};
