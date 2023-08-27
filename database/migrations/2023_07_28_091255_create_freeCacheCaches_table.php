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
        Schema::create('freeCacheCaches', function (Blueprint $table) {
            $table->id();
            $table->date('dateRepresented'); // Date that this cache represents (yyyy-mm-dd)
            $table->text('timezone'); // Timezone that this cache represents (e.g. Europe/London)
            $table->text('discordUsers'); // Comma-separated list of Discord users whose mutual free time this cache represents
            $table->unsignedInteger('createdAt')->default(0); // Unix timestamp of when this cache was created
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('freeCacheCaches');
    }
};
