<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            // Public website generator: only is_public packages appear on the tenant's
            // auto-generated site. Defaults true so existing packages keep showing as before.
            $table->boolean('is_public')->default(true)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
