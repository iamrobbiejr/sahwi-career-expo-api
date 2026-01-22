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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_registration_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_id')->nullable()->constrained()->onDelete('set null');

            $table->string('ticket_number')->unique();
            $table->string('qr_code_path')->nullable(); // Path to QR code image
            $table->string('pdf_path')->nullable(); // Path to PDF ticket

            // Ticket Status
            $table->enum('status', ['active', 'used', 'cancelled', 'expired'])->default('active');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by')->nullable()->constrained('users')->onDelete('set null'); // Staff who scanned

            // Delivery
            $table->timestamp('emailed_at')->nullable();
            $table->integer('email_attempts')->default(0);

            $table->timestamps();

            $table->index('ticket_number');
            $table->index(['event_registration_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
