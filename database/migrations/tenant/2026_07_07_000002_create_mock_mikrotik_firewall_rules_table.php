<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_mikrotik_firewall_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mikrotik_id')->constrained()->cascadeOnDelete();

            $table->string('chain');
            $table->string('action');
            $table->string('protocol')->nullable();
            $table->string('dst_port')->nullable();
            $table->string('src_address')->nullable();
            $table->string('dst_address')->nullable();
            $table->string('comment')->nullable();
            $table->boolean('disabled')->default(false);
            // App-managed rule order (RouterOS itself orders firewall rules positionally with no
            // separate "position" field) - lets the mock reorder without a real chain to reorder.
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_mikrotik_firewall_rules');
    }
};
