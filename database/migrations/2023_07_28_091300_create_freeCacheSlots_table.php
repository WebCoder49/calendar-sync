<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration // The "freeCache" is a caching system for free slots by day, specific to timezone and date, that is kept until it is deemed too old.
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('freeCacheSlots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cacheID'); // ID of the freecache_caches cache that this slot belongs to
            $table->unsignedSmallInteger('startTime'); // Start time (mins-since-midnight) of free slot
            $table->unsignedSmallInteger('endTime'); // End time (mins-since-midnight) of free slot
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('freeCacheSlots');
    }
};
