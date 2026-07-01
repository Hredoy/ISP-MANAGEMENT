<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE tenant_applications MODIFY status VARCHAR(50) NOT NULL DEFAULT 'pending'");
        }

        Schema::table('tenant_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('tenant_applications', 'owner_name')) {
                $table->string('owner_name')->nullable()->after('organization_name');
            }

            if (! Schema::hasColumn('tenant_applications', 'address')) {
                $table->text('address')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('tenant_applications', 'domain_request')) {
                $table->string('domain_request')->nullable()->after('address');
            }

            if (! Schema::hasColumn('tenant_applications', 'business_type')) {
                $table->string('business_type')->nullable()->after('domain_request');
            }

            if (! Schema::hasColumn('tenant_applications', 'package_request')) {
                $table->string('package_request')->nullable()->after('business_type');
            }

            if (! Schema::hasColumn('tenant_applications', 'module_request')) {
                $table->json('module_request')->nullable()->after('package_request');
            }

            if (! Schema::hasColumn('tenant_applications', 'notes')) {
                $table->text('notes')->nullable()->after('module_request');
            }

            if (! Schema::hasColumn('tenant_applications', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('tenant_applications', 'converted_at')) {
                $table->timestamp('converted_at')->nullable()->after('approved_at');
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'organization_name')) {
                $table->string('organization_name')->nullable()->after('id');
            }

            if (! Schema::hasColumn('tenants', 'owner_name')) {
                $table->string('owner_name')->nullable()->after('organization_name');
            }

            if (! Schema::hasColumn('tenants', 'admin_email')) {
                $table->string('admin_email')->nullable()->after('owner_name');
            }

            if (! Schema::hasColumn('tenants', 'status')) {
                $table->string('status')->default('pending_setup')->after('admin_email');
            }

            if (! Schema::hasColumn('tenants', 'database_name')) {
                $table->string('database_name')->nullable()->after('status');
            }

            if (! Schema::hasColumn('tenants', 'database_status')) {
                $table->string('database_status')->default('pending')->after('database_name');
            }

            if (! Schema::hasColumn('tenants', 'domain_status')) {
                $table->string('domain_status')->default('pending')->after('database_status');
            }

            if (! Schema::hasColumn('tenants', 'suspended_message')) {
                $table->text('suspended_message')->nullable()->after('domain_status');
            }
        });

        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('modules')->insert([
            ['name' => 'Customers', 'slug' => 'customers', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Packages', 'slug' => 'packages', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Billing', 'slug' => 'billing', 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Payments', 'slug' => 'payments', 'sort_order' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'MikroTik', 'slug' => 'mikrotik', 'sort_order' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Support Tickets', 'slug' => 'support-tickets', 'sort_order' => 6, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'SMS', 'slug' => 'sms', 'sort_order' => 7, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Reports', 'slug' => 'reports', 'sort_order' => 8, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Employees', 'slug' => 'employees', 'sort_order' => 9, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Accounting', 'slug' => 'accounting', 'sort_order' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Inventory', 'slug' => 'inventory', 'sort_order' => 11, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Settings', 'slug' => 'settings', 'sort_order' => 12, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'module_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('tenant_provisioning_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->foreignId('tenant_application_id')->nullable()->constrained('tenant_applications')->nullOnDelete();
            $table->string('step');
            $table->string('status')->default('pending');
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });

        Schema::create('tenant_packages', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('status')->default('active');
            $table->json('limits')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('tenant_package_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['tenant_package_id', 'module_id']);
        });

        Schema::create('landlord_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('tenant_id')->nullable();
            $table->foreignId('tenant_application_id')->nullable()->constrained('tenant_applications')->nullOnDelete();
            $table->string('action');
            $table->text('message')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landlord_audit_logs');
        Schema::dropIfExists('tenant_package_modules');
        Schema::dropIfExists('tenant_packages');
        Schema::dropIfExists('tenant_provisioning_logs');
        Schema::dropIfExists('tenant_settings');
        Schema::dropIfExists('tenant_modules');
        Schema::dropIfExists('modules');
    }
};
