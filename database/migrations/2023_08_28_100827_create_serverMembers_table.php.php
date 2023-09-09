<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration // The "serverMembers" table is a storage system for CalSync-registered members of a Discord server, that is updated only when somebody loads the server web view.
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('serverMembers', function (Blueprint $table) {
            $table->unsignedBigInteger('serverID')->primary();
            $table->text('discordUsers'); // Members of the server as a comma-separated list.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('serverMembers');
    }
};
