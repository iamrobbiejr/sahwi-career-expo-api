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
        Schema::create('forum_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('forum_post_id');
            $table->unsignedBigInteger('author_id');
            $table->text('content');
            $table->string('status')->default('published'); // published, pending, rejected, deleted

            // Threading support
            $table->unsignedBigInteger('parent_comment_id')->nullable();
            $table->integer('depth')->default(0);
            $table->string('thread_path')->nullable(); // e.g., "1/5/12" for nested comments

            // Attachments
            $table->json('attachments')->nullable();

            // Moderation
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Mentions
            $table->json('mentions')->nullable();

            // Statistics
            $table->integer('like_count')->default(0);
            $table->integer('reply_count')->default(0);

            // Edit tracking
            $table->timestamp('edited_at')->nullable();
            $table->unsignedBigInteger('edited_by')->nullable();
            $table->text('original_content')->nullable();

            // Indexes
            $table->index('forum_post_id');
            $table->index('author_id');
            $table->index('parent_comment_id');
            $table->index('status');
            $table->index('created_at');
            $table->index(['forum_post_id', 'parent_comment_id']);
            $table->index('thread_path');

            // Foreign Keys
            $table->foreign('forum_post_id')->references('id')->on('forum_posts')->onDelete('cascade');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('parent_comment_id')->references('id')->on('forum_comments')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('edited_by')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forum_comments');
    }
};
