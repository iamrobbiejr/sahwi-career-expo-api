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
        Schema::create('thread_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thread_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role')->default('member'); // member, moderator, owner
            $table->string('status')->default('active'); // active, muted, blocked, left

            // Read tracking
            $table->timestamp('last_read_at')->nullable();
            $table->integer('unread_count')->default(0);

            // Notifications
            $table->boolean('notifications_enabled')->default(true);
            $table->boolean('muted')->default(false);

            // Permissions
            $table->boolean('can_send_messages')->default(true);
            $table->boolean('can_add_members')->default(false);
            $table->boolean('can_remove_members')->default(false);

            // Metadata
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->json('settings')->nullable();

            // Indexes
            $table->index('thread_id');
            $table->index('user_id');
            $table->index(['thread_id', 'user_id']);
            $table->index('role');
            $table->index('status');

            // Foreign Keys
            $table->foreign('thread_id')->references('id')->on('threads')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Unique constraint
            $table->unique(['thread_id', 'user_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thread_members');
    }
};
