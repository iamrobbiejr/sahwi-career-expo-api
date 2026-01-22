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
        Schema::create('conference_calls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');

            // Meeting Platform Details
            $table->string('platform')->default('zoom'); // zoom, teams, google_meet, webex, custom
            $table->string('meeting_url')->nullable();
            $table->string('meeting_id')->nullable();
            $table->string('passcode')->nullable();
            $table->text('dial_in_numbers')->nullable(); // JSON array of phone numbers

            // Host Information
            $table->string('host_name')->nullable();
            $table->string('host_email')->nullable();
            $table->string('host_id')->nullable(); // Platform-specific host ID

            // Meeting Settings
            $table->boolean('waiting_room_enabled')->default(true);
            $table->boolean('recording_enabled')->default(false);
            $table->string('recording_url')->nullable();
            $table->boolean('auto_recording')->default(false);
            $table->string('auto_recording_type')->nullable(); // cloud, local

            // Security Settings
            $table->boolean('require_registration')->default(false);
            $table->boolean('mute_on_entry')->default(false);
            $table->boolean('screen_sharing')->default(true);
            $table->string('participant_video')->default('enabled'); // enabled, disabled, optional

            // Integration Data (for API integrations)
            $table->text('platform_meeting_data')->nullable(); // JSON for platform-specific data
            $table->string('platform_meeting_uuid')->nullable();

            // Meeting Metadata
            $table->integer('max_participants')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('scheduled_end')->nullable();
            $table->timestamp('actual_start')->nullable();
            $table->timestamp('actual_end')->nullable();

            // Status
            $table->string('status')->default('scheduled'); // scheduled, live, ended, cancelled
            $table->text('cancellation_reason')->nullable();

            // Additional Features
            $table->text('instructions')->nullable(); // Custom instructions for participants
            $table->boolean('send_reminders')->default(true);
            $table->integer('reminder_minutes_before')->default(15);

            // Indexes
            $table->index('event_id');
            $table->index('platform');
            $table->index('status');
            $table->index('scheduled_start');

            // Foreign Key
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes(); // For soft deletion
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conference_calls');
    }
};
