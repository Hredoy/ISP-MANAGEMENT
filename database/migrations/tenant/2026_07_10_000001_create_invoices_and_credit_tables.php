<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('payment_id')->nullable()->index();
            $table->unsignedInteger('sequence_number');
            $table->string('invoice_number')->unique();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('status')->default('issued')->index(); // draft|issued|paid|cancelled
            $table->string('pdf_path')->nullable();
            $table->timestamp('issued_at')->nullable()->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('client_id');
            $table->index('status');
        });

        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->uuid('payment_id')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->string('type')->index(); // credit | debit
            $table->string('reason');
            $table->decimal('balance_after', 12, 2);
            $table->timestamps();

            $table->index('client_id');
        });

        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'credit_balance')) {
                $table->decimal('credit_balance', 12, 2)->default(0)->after('monthly_bill');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('credit_balance');
        });
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('invoices');
    }
};
