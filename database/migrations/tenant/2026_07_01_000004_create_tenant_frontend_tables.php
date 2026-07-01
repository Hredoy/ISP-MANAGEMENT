<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_frontend_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_frontend_sliders', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('button_text')->nullable();
            $table->string('button_url')->nullable();
            $table->string('background_image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tenant_frontend_sections', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('link')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_connection_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->text('address')->nullable();
            $table->string('area')->nullable();
            $table->string('package')->nullable();
            $table->date('preferred_connection_date')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('pending');
            $table->string('visitor_ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_complaints', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id')->nullable();
            $table->string('phone');
            $table->string('complaint_type');
            $table->text('message');
            $table->string('image_path')->nullable();
            $table->string('status')->default('pending');
            $table->string('visitor_ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_referrals', function (Blueprint $table) {
            $table->id();
            $table->string('friend_name');
            $table->string('friend_phone');
            $table->string('referrer_user_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('visitor_ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_manual_payments', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id');
            $table->decimal('amount', 12, 2);
            $table->string('transaction_id');
            $table->string('payment_method');
            $table->string('screenshot_path')->nullable();
            $table->string('status')->default('pending');
            $table->string('visitor_ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_blogs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_blogs');
        Schema::dropIfExists('tenant_manual_payments');
        Schema::dropIfExists('tenant_referrals');
        Schema::dropIfExists('tenant_complaints');
        Schema::dropIfExists('tenant_connection_requests');
        Schema::dropIfExists('tenant_frontend_sections');
        Schema::dropIfExists('tenant_frontend_sliders');
        Schema::dropIfExists('tenant_frontend_settings');
    }
};
