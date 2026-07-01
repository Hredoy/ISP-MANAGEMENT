<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_mikrotik_interfaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mikrotik_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('type')->default('ether');
            $table->boolean('running')->default(true);
            $table->boolean('disabled')->default(false);
            $table->string('mac_address')->nullable();
            $table->unsignedBigInteger('rx_bytes')->default(0);
            $table->unsignedBigInteger('tx_bytes')->default(0);
            $table->unsignedBigInteger('rx_bps')->default(0);
            $table->unsignedBigInteger('tx_bps')->default(0);
            $table->timestamps();

            $table->unique(['mikrotik_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_mikrotik_interfaces');
    }
};
