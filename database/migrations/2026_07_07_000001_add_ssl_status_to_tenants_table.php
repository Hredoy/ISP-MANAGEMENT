<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'ssl_status')) {
                // Only meaningful for a custom domain (the free {slug}.yourplatform.com
                // subdomain is served by an existing wildcard vhost/cert and never needs this).
                $table->string('ssl_status')->default('not_applicable')->after('domain_status');
            }

            if (! Schema::hasColumn('tenants', 'ssl_last_checked_at')) {
                $table->timestamp('ssl_last_checked_at')->nullable()->after('ssl_status');
            }

            if (! Schema::hasColumn('tenants', 'ssl_issued_at')) {
                $table->timestamp('ssl_issued_at')->nullable()->after('ssl_last_checked_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['ssl_status', 'ssl_last_checked_at', 'ssl_issued_at']);
        });
    }
};
