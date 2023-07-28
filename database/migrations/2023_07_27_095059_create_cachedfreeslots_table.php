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
            $table->id();
            $table->unsignedBigInteger('server_id');
            $table->unsignedInteger('updated_at')->default(0);
            $table->string('day');
            $table->string('timezone'); // Minimize processing of cached data; many servers will have many people from the same timezone so fine to be timezone-specific.

            $table->unsignedSmallInteger('start'); // Mins since midnight
            $table->unsignedSmallInteger('end'); // Mins since midnight
            $table->string('description');
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
