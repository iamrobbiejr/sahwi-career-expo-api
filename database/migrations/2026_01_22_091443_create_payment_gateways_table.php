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
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Paynow, Paypal, Stripe, etc.
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);

            // Gateway Configuration
            $table->jsonb('credentials')->nullable(); // Encrypted gateway credentials
            $table->jsonb('settings')->nullable(); // Gateway-specific settings

            // Supported features
            $table->boolean('supports_webhooks')->default(false);
            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret')->nullable();

            // Currency support
            $table->jsonb('supported_currencies')->nullable();

            $table->timestamps();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
