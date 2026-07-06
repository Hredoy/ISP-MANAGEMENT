<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Set when the billing scheduler throttles an unpaid client at expiry (D-0) so it
            // can tell which clients to restore to full speed once they've paid/renewed.
            $table->timestamp('throttled_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('throttled_at');
        });
    }
};
