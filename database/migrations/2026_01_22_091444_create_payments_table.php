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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_gateway_id')->nullable()->constrained()->onDelete('set null');

            // Payment Details
            $table->string('payment_reference')->unique(); // Internal reference
            $table->string('gateway_transaction_id')->nullable()->index(); // Gateway's transaction ID
            $table->string('gateway_name')->nullable(); // Store gateway name for reference

            // Amount
            $table->integer('amount_cents');
            $table->string('currency', 3);
            $table->integer('gateway_fee_cents')->default(0);
            $table->integer('platform_fee_cents')->default(0);

            // Status
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
                'cancelled',
                'refunded',
                'partially_refunded'
            ])->default('pending');

            // Payment Method
            $table->string('payment_method')->nullable(); // card, mobile_money, bank_transfer
            $table->string('payment_phone')->nullable(); // For mobile money

            // Metadata
            $table->jsonb('gateway_response')->nullable(); // Store raw gateway response
            $table->text('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            // Related Data
            $table->text('notes')->nullable();
            $table->string('receipt_url')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['event_id', 'status']);
            $table->index('status');
            $table->index('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
