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
        Schema::create('forums', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('public')->default(true);
            $table->json('moderation_policy')->nullable();

            // Forum settings
            $table->boolean('is_active')->default(true);
            $table->boolean('allow_posts')->default(true);
            $table->boolean('require_approval')->default(false);
            $table->string('icon')->nullable();
            $table->string('banner_image')->nullable();
            $table->integer('display_order')->default(0);

            // Parent forum (for sub-forums)
            $table->unsignedBigInteger('parent_forum_id')->nullable();

            // Moderators
            $table->unsignedBigInteger('created_by');
            $table->json('moderator_ids')->nullable();

            // Statistics
            $table->integer('post_count')->default(0);
            $table->integer('comment_count')->default(0);
            $table->integer('member_count')->default(0);
            $table->timestamp('last_post_at')->nullable();

            // Categories/Tags
            $table->json('categories')->nullable();

            // Indexes
            $table->index('slug');
            $table->index('public');
            $table->index('is_active');
            $table->index('parent_forum_id');
            $table->index('created_by');
            $table->index('display_order');

            // Foreign Keys
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('parent_forum_id')->references('id')->on('forums')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forums');
    }
};
