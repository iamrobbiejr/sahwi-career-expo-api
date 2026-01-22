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
        Schema::create('forum_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('forum_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role')->default('member'); // member, moderator, admin
            $table->string('status')->default('active'); // active, banned, suspended

            // Permissions
            $table->boolean('can_post')->default(true);
            $table->boolean('can_comment')->default(true);
            $table->boolean('can_moderate')->default(false);

            // Notifications
            $table->boolean('notifications_enabled')->default(true);
            $table->string('notification_frequency')->default('instant'); // instant, daily, weekly

            // Statistics
            $table->integer('post_count')->default(0);
            $table->integer('comment_count')->default(0);
            $table->timestamp('last_activity_at')->nullable();

            // Ban/Suspension
            $table->timestamp('banned_until')->nullable();
            $table->text('ban_reason')->nullable();
            $table->unsignedBigInteger('banned_by')->nullable();

            // Indexes
            $table->index('forum_id');
            $table->index('user_id');
            $table->index(['forum_id', 'user_id']);
            $table->index('role');
            $table->index('status');

            // Foreign Keys
            $table->foreign('forum_id')->references('id')->on('forums')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('banned_by')->references('id')->on('users')->onDelete('set null');

            // Unique constraint
            $table->unique(['forum_id', 'user_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forum_members');
    }
};
