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
        Schema::create('threads', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('thread_type'); // direct, group, forum, event_channel
            $table->unsignedBigInteger('created_by');
            $table->json('meta')->nullable(); // e.g., event_id, forum_id, etc.

            // Thread settings
            $table->boolean('is_active')->default(true);
            $table->boolean('is_archived')->default(false);
            $table->boolean('allow_attachments')->default(true);
            $table->integer('max_members')->nullable();

            // Statistics
            $table->integer('message_count')->default(0);
            $table->integer('member_count')->default(0);
            $table->timestamp('last_message_at')->nullable();

            // Indexes
            $table->index('thread_type');
            $table->index('created_by');
            $table->index('is_active');
            $table->index('last_message_at');

            // Foreign Keys
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('threads');
    }
};
