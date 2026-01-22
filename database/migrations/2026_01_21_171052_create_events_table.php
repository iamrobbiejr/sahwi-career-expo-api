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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->text('img')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('venue')->nullable();
            $table->string('status')->default('draft'); // active, draft, completed, cancelled
            $table->integer('registrations')->default(0);
            $table->integer('capacity')->nullable();
            $table->date('registration_deadline')->nullable();
            $table->unsignedBigInteger('created_by');

            $table->string('location')->nullable(); // In-Person, Virtual
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->integer('price_cents')->nullable();
            $table->string('currency')->nullable();
            // Indexes
            $table->index('status');
            $table->index('created_by');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
