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
        Schema::create('email_broadcast_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('email_broadcast_id');
            $table->string('level'); // info, warning, error
            $table->text('message');
            $table->json('context')->nullable();
            $table->string('event_type')->nullable(); // started, completed, failed, recipient_sent, etc.

            // Indexes
            $table->index('email_broadcast_id');
            $table->index('level');
            $table->index('event_type');

            // Foreign Keys
            $table->foreign('email_broadcast_id')->references('id')->on('email_broadcasts')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_broadcast_logs');
    }
};
