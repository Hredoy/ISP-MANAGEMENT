<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_mikrotik_system', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mikrotik_id')->unique()->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('cpu_load')->default(0);
            $table->unsignedBigInteger('free_memory')->default(0);
            $table->unsignedBigInteger('total_memory')->default(0);
            $table->unsignedBigInteger('free_hdd_space')->default(0);
            $table->unsignedBigInteger('total_hdd_space')->default(0);
            $table->unsignedBigInteger('uptime_seconds')->default(0);
            $table->string('board_name')->nullable()->default('CHR');
            $table->string('version')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_mikrotik_system');
    }
};
