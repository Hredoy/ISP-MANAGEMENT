<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('vendor'); // huawei, zte, vsol
            $table->string('host');
            $table->integer('port')->default(23);
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('snmp_community')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_ping')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olts');
    }
};
