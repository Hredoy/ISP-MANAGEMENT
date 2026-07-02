<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RouterOS connection-error messages (e.g. socket/timeout failures) routinely exceed the default
 * 255-char `string` column - widen to `text` so activity logging never fails on a long message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mikrotik_activity_logs', function (Blueprint $table) {
            $table->text('description')->change();
        });
    }

    public function down(): void
    {
        Schema::table('mikrotik_activity_logs', function (Blueprint $table) {
            $table->string('description')->change();
        });
    }
};
