<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mikrotik_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mikrotik_id')->constrained()->cascadeOnDelete();

            $table->string('event_type');
            $table->string('description');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mikrotik_activity_logs');
    }
};
