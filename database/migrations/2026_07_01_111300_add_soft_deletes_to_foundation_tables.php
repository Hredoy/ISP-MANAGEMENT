<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->softDeletes();
            $table->index('deleted_at');
        });

        Schema::table('domains', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->softDeletes();
            $table->index(['tenant_id', 'domain']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        DB::table('domains')
            ->whereNull('uuid')
            ->orderBy('id')
            ->get(['id'])
            ->each(fn ($domain) => DB::table('domains')->where('id', $domain->id)->update(['uuid' => (string) Str::uuid()]));
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('domains', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'domain']);
            $table->dropSoftDeletes();
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropSoftDeletes();
        });
    }
};
