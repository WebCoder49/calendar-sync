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
        Schema::create('cachedfreeslots', function (Blueprint $table) {
            $table->string('server_and_day', 31)->primary(); // What it's referring to: Server id + - + yyyy-mm-dd, e.g. 12345678901234567890-2023-07-17
            $table->unsignedInteger('free_slot_cache')->default(0); // 'List' of mins-since-midnight times of day, [start slot 1, end 1, start 2, end 2, start 3...]
            // This "list" is encoded as a binary number, with the ith item being multiplied by 2^(11*i), and all being added together
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cachedfreeslots');
    }
};
