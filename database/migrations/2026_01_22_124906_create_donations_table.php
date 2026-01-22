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
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('donation_campaigns')->onDelete('cascade');
            $table->foreignId('donor_id')->nullable()->constrained('users')->onDelete('set null'); // Nullable for anonymous donations
            $table->unsignedBigInteger('amount_cents');
            $table->string('donor_name')->nullable(); // For anonymous or guest donors
            $table->string('donor_email')->nullable();
            $table->text('message')->nullable(); // Optional message from a donor
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->string('payment_method')->nullable(); // e.g., 'credit_card', 'paypal', 'bank_transfer'
            $table->string('transaction_id')->nullable()->unique(); // External payment reference
            $table->boolean('anonymous')->default(false); // Hide donor identity publicly
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('campaign_id');
            $table->index('donor_id');
            $table->index('status');
            $table->index('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
