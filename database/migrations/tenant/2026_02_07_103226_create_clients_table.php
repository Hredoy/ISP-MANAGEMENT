<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            // Connection Logic
            $table->foreignId('mikrotik_id')->constrained()->onDelete('cascade');
            $table->foreignId('zone_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('sub_zone_id')->nullable()->constrained()->onDelete('set null');

            // PPPoE Details
            $table->string('pppoe_username')->unique();
            $table->string('pppoe_password');
            $table->string('package_name'); // e.g., 5Mbps_Unlimited

            // Personal Details
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone_number');
            $table->string('telegram_chat_id')->nullable();

            // Billing & Status
            $table->decimal('monthly_bill', 10, 2);
            $table->text('full_address');
            $table->date('expiry_date');
            $table->string('status')->default('Inactive'); // Active, Inactive, Expired
            $table->text('additional_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
