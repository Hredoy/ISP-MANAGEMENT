<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('tenant_applications', 'district')) {
                $table->string('district')->nullable()->after('slug');
            }

            if (! Schema::hasColumn('tenant_applications', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('district');
            }

            if (! Schema::hasColumn('tenant_applications', 'plan')) {
                $table->string('plan')->default('starter')->after('logo_path');
            }

            if (! Schema::hasColumn('tenant_applications', 'mikrotik_ip')) {
                $table->string('mikrotik_ip')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('tenant_applications', 'olt_ip')) {
                $table->string('olt_ip')->nullable()->after('mikrotik_ip');
            }

            if (! Schema::hasColumn('tenant_applications', 'olt_brand')) {
                $table->string('olt_brand')->nullable()->after('olt_ip');
            }

            if (! Schema::hasColumn('tenant_applications', 'custom_domain')) {
                $table->string('custom_domain')->nullable()->unique()->after('olt_brand');
            }

            if (! Schema::hasColumn('tenant_applications', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->unique()->after('status');
            }

            if (! Schema::hasColumn('tenant_applications', 'admin_email')) {
                $table->string('admin_email')->nullable()->after('subdomain');
            }

            if (! Schema::hasColumn('tenant_applications', 'sms_sent_at')) {
                $table->timestamp('sms_sent_at')->nullable()->after('admin_email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_applications', function (Blueprint $table) {
            foreach (['district', 'logo_path', 'plan', 'mikrotik_ip', 'olt_ip', 'olt_brand', 'admin_email', 'sms_sent_at'] as $column) {
                if (Schema::hasColumn('tenant_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
