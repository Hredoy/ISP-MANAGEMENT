<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // AI-assisted keyword categorization (Connectivity/Speed/Billing/Other), and the SLA
            // deadline used by the escalation scheduler (urgent: +2h, normal: +24h from creation).
            $table->string('category')->default('Other')->after('subject');
            $table->timestamp('sla_due_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['category', 'sla_due_at']);
        });
    }
};
