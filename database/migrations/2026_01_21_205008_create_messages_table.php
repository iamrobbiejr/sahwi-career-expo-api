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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thread_id');
            $table->unsignedBigInteger('sender_id');
            $table->text('content');
            $table->json('attachments')->nullable();
            $table->unsignedBigInteger('reply_to_message_id')->nullable();
            $table->string('status')->default('sent'); // sent, edited, deleted

            // Message type
            $table->string('message_type')->default('text'); // text, image, file, system

            // Mentions and reactions
            $table->json('mentions')->nullable(); // User IDs mentioned in message
            $table->json('reactions')->nullable(); // emoji reactions

            // Edit tracking
            $table->timestamp('edited_at')->nullable();
            $table->text('original_content')->nullable();

            // Read receipts
            $table->json('read_by')->nullable(); // User IDs who read the message
            $table->integer('read_count')->default(0);

            // Metadata
            $table->json('metadata')->nullable();

            // Indexes
            $table->index('thread_id');
            $table->index('sender_id');
            $table->index('reply_to_message_id');
            $table->index('status');
            $table->index('created_at');
            $table->index(['thread_id', 'created_at']);

            // Foreign Keys
            $table->foreign('thread_id')->references('id')->on('threads')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reply_to_message_id')->references('id')->on('messages')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
