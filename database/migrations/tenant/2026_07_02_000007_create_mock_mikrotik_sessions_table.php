<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_mikrotik_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mikrotik_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mock_mikrotik_user_id')->constrained('mock_mikrotik_users')->cascadeOnDelete();

            $table->string('username');
            $table->string('address')->nullable();
            $table->string('caller_id')->nullable();
            $table->unsignedInteger('uptime_seconds')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_mikrotik_sessions');
    }
};
