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
        Schema::create('email_broadcast_recipients', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('email_broadcast_id');
            $table->unsignedBigInteger('user_id');
            $table->string('email');

            // Delivery Status
            $table->string('status')->default('pending'); // pending, sent, failed, bounced
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);

            // Tracking
            $table->boolean('opened')->default(false);
            $table->timestamp('opened_at')->nullable();
            $table->integer('open_count')->default(0);
            $table->boolean('clicked')->default(false);
            $table->timestamp('clicked_at')->nullable();
            $table->integer('click_count')->default(0);

            // Metadata
            $table->json('metadata')->nullable();

            // Indexes
            $table->index('email_broadcast_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('sent_at');

            // Foreign Keys
            $table->foreign('email_broadcast_id')->references('id')->on('email_broadcasts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_broadcast_recipients');
    }
};
