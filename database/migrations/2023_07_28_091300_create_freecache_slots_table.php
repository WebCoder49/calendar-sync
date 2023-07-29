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
        Schema::create('freecache_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cache_id'); // ID of the freecache_caches cache that this slot belongs to
            $table->unsignedSmallInteger('starttime'); // Start time (mins-since-midnight) of free slot
            $table->unsignedSmallInteger('endtime'); // End time (mins-since-midnight) of free slot
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('freecache_slots');
    }
};
