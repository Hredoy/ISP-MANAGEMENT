<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_mikrotik_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mikrotik_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('rate_limit')->nullable();
            $table->string('local_address')->nullable();
            $table->string('remote_address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['mikrotik_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_mikrotik_profiles');
    }
};
