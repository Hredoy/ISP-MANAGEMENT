<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faults', function (Blueprint $table) {
            // The pre-existing `device_id` column is a uuid FK into the generic (and currently
            // unused/unpopulated) `devices` table, which doesn't fit the real, already-integer-
            // keyed `mikrotiks` table this polling engine actually monitors - so this adds a
            // proper FK for what's really populated instead of forcing data into `devices`.
            $table->foreignId('mikrotik_id')->nullable()->after('device_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('faults', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mikrotik_id');
        });
    }
};
