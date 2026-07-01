<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('olt_id')->nullable()->after('mikrotik_id')->constrained()->onDelete('set null');
            $table->string('onu_mac')->nullable()->after('olt_id');
            $table->string('onu_serial')->nullable()->after('onu_mac');
            $table->string('pon_port')->nullable()->after('onu_serial');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('olt_id');
            $table->dropColumn(['onu_mac', 'onu_serial', 'pon_port']);
        });
    }
};
