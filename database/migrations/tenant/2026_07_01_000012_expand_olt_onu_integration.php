<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('olts', function (Blueprint $table) {
            $table->string('sys_descr')->nullable()->after('snmp_community');
            $table->timestamp('last_onu_sync_at')->nullable()->after('last_ping');
            $table->index('vendor');
        });

        Schema::table('onus', function (Blueprint $table) {
            $table->foreignId('olt_id')->nullable()->after('id')->constrained('olts')->nullOnDelete();
            $table->string('onu_id')->nullable()->after('pon_port')->index();
            $table->decimal('rx_dbm', 8, 2)->nullable()->after('status');
            $table->decimal('tx_dbm', 8, 2)->nullable()->after('rx_dbm');
            $table->string('signal_color')->nullable()->after('tx_dbm')->index();
            $table->string('signal_label')->nullable()->after('signal_color');
            $table->timestamp('last_seen_at')->nullable()->after('signal_metrics')->index();
            $table->index(['olt_id', 'pon_port']);
        });
    }

    public function down(): void
    {
        Schema::table('onus', function (Blueprint $table) {
            $table->dropIndex(['olt_id', 'pon_port']);
            $table->dropColumn(['olt_id', 'onu_id', 'rx_dbm', 'tx_dbm', 'signal_color', 'signal_label', 'last_seen_at']);
        });

        Schema::table('olts', function (Blueprint $table) {
            $table->dropIndex(['vendor']);
            $table->dropColumn(['sys_descr', 'last_onu_sync_at']);
        });
    }
};
