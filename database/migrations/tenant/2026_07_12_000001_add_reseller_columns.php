<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->uuid('parent_reseller_id')->nullable()->after('user_id');
            $table->decimal('wallet_balance', 12, 2)->default(0)->after('commission_rate');
            $table->foreign('parent_reseller_id')->references('id')->on('resellers')->nullOnDelete();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->uuid('reseller_id')->nullable()->after('id');
            $table->foreign('reseller_id')->references('id')->on('resellers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['reseller_id']);
            $table->dropColumn('reseller_id');
        });

        Schema::table('resellers', function (Blueprint $table) {
            $table->dropForeign(['parent_reseller_id']);
            $table->dropColumn(['parent_reseller_id', 'wallet_balance']);
        });
    }
};
