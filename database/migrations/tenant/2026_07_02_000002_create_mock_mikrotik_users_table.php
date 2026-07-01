<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_mikrotik_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mikrotik_id')->constrained()->cascadeOnDelete();

            $table->string('username');
            $table->string('password');
            $table->string('profile')->default('default');
            $table->string('service')->default('pppoe');
            $table->boolean('disabled')->default(false);
            $table->string('comment')->nullable();
            $table->string('local_address')->nullable();
            $table->string('remote_address')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('caller_id')->nullable();
            $table->unsignedBigInteger('bytes_in')->default(0);
            $table->unsignedBigInteger('bytes_out')->default(0);
            $table->timestamps();

            $table->unique(['mikrotik_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_mikrotik_users');
    }
};
