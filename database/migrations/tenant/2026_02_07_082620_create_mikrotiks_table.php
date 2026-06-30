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
        Schema::create('mikrotiks', function (Blueprint $table) {
            $table->id();

            // The display name for the node (e.g., Core_Router_01)
            $table->string('name');

            // Connection details
            $table->string('host'); // IP Address or Domain
            $table->integer('port')->default(8728); // Default RouterOS API port
            $table->string('username');
            $table->string('password'); // Note: In a real app, consider encrypting this

            // Metadata for the "Hacker" Dashboard
            $table->string('sitename')->nullable(); // Location/Branch
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_ping')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mikrotiks');
    }
};
