<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_mikrotik_queue_trees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mikrotik_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('parent')->default('global');
            $table->string('packet_mark')->nullable();
            $table->string('max_limit');
            $table->string('limit_at')->nullable();
            $table->unsignedTinyInteger('priority')->default(8);
            $table->boolean('disabled')->default(false);
            $table->timestamps();

            $table->unique(['mikrotik_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_mikrotik_queue_trees');
    }
};
