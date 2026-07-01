<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_mikrotik_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mikrotik_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('target');
            $table->string('max_limit');
            $table->unsignedBigInteger('bytes_in')->default(0);
            $table->unsignedBigInteger('bytes_out')->default(0);
            $table->boolean('disabled')->default(false);
            $table->timestamps();

            $table->unique(['mikrotik_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_mikrotik_queues');
    }
};
