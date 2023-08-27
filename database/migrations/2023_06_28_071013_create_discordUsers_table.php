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
        Schema::create('discordUsers', function (Blueprint $table) {
            $table->unsignedBigInteger('discordID')->primary();

            $table->unsignedSmallInteger('settingsActiveHoursStart');
            $table->unsignedSmallInteger('settingsActiveHoursEnd');
            $table->text('settingsPreferencesTimezone');

            $table->text('settingsCalendarSelectedCalendars');

            $table->string('calauthType', 3)->default("");
            $table->string('calauthAccessToken', 2048)->default("");
            $table->string('calauthRefreshToken', 512)->default("");
            $table->unsignedInteger('calauthExpiresAt')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discordUsers');
    }
};
