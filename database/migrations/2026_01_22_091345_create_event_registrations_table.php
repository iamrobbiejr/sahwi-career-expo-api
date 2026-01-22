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
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('registered_by')->nullable()->constrained('users')->onDelete('set null'); // For company_rep registering others

            // Registration Details
            $table->string('registration_type'); // individual, group
            $table->string('attendee_type'); // student, professional, company_rep
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'waitlisted'])->default('pending');

            // Attendee Information (can differ from the user if registered by company_rep)
            $table->string('attendee_name');
            $table->string('attendee_email');
            $table->string('attendee_phone')->nullable();
            $table->string('attendee_title')->nullable();
            $table->foreignId('attendee_organization_id')->nullable()->constrained('organizations')->onDelete('set null');

            // Additional fields
            $table->text('special_requirements')->nullable();
            $table->jsonb('custom_fields')->nullable(); // For event-specific questions

            // Ticket Information
            $table->string('ticket_number')->unique()->nullable();
            $table->timestamp('ticket_generated_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();

            // Timestamps
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['event_id', 'status']);
            $table->index('ticket_number');
            $table->index(['user_id', 'event_id']);
            $table->unique(['event_id', 'attendee_email']); // Prevent duplicate registrations
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
