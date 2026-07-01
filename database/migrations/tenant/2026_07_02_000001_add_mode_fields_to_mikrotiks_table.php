<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mikrotiks', function (Blueprint $table) {
            $table->integer('ssl_port')->nullable()->default(8729)->after('port');
            $table->string('connection_type')->default('api')->after('ssl_port'); // api|api_ssl
            $table->string('router_version')->nullable()->after('connection_type');
            $table->string('timezone')->nullable()->default('UTC')->after('router_version');
            $table->string('status')->default('unknown')->after('timezone'); // online|offline|unknown, cached
            $table->string('mode')->default('use_global')->after('status'); // demo|real|use_global
            $table->boolean('is_default')->default(false)->after('mode');
            $table->timestamp('last_connected_at')->nullable()->after('last_ping');
            $table->timestamp('last_sync_at')->nullable()->after('last_pppoe_sync_at');
            $table->foreignId('created_by')->nullable()->after('last_sync_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mikrotiks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'ssl_port',
                'connection_type',
                'router_version',
                'timezone',
                'status',
                'mode',
                'is_default',
                'last_connected_at',
                'last_sync_at',
            ]);
        });
    }
};
