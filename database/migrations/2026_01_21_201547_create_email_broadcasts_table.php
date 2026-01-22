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
        Schema::create('email_broadcasts', function (Blueprint $table) {
            $table->id();

            // Sender Information
            $table->unsignedBigInteger('sender_id');
            $table->string('sender_type'); // admin, company, university
            $table->unsignedBigInteger('sender_entity_id')->nullable(); // company_id or university_id

            // Email Content
            $table->string('subject');
            $table->longText('message');
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('reply_to_email')->nullable();

            // Audience Targeting
            $table->string('audience_type'); // all_users, university_interested, event_registered, custom
            $table->unsignedBigInteger('target_university_id')->nullable();
            $table->unsignedBigInteger('target_event_id')->nullable();
            $table->json('custom_user_ids')->nullable(); // For a custom audience

            // Filtering Options
            $table->json('filters')->nullable(); // Additional filters like user_type, registration_date, etc.

            // Scheduling
            $table->timestamp('scheduled_at')->nullable();
            $table->boolean('is_scheduled')->default(false);

            // Status & Statistics
            $table->string('status')->default('draft'); // draft, queued, processing, completed, failed, cancelled
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('opened_count')->default(0);
            $table->integer('clicked_count')->default(0);

            // Processing Metadata
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('processing_stats')->nullable();

            // Attachments
            $table->json('attachments')->nullable(); // Array of file paths

            // Template
            $table->string('template')->nullable(); // Email template name

            // Tracking
            $table->boolean('track_opens')->default(true);
            $table->boolean('track_clicks')->default(true);
            $table->string('tracking_id')->unique()->nullable();

            // Indexes
            $table->index('sender_id');
            $table->index('sender_type');
            $table->index('audience_type');
            $table->index('status');
            $table->index('scheduled_at');
            $table->index('tracking_id');

            // Foreign Keys
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_broadcasts');
    }
};
