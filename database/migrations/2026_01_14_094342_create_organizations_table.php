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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['company', 'university']);
            $table->string('name');
            $table->boolean('verified')->default(false);
            $table->jsonb('verification_docs')->nullable(); // Store the list of documents as JSONB
            $table->timestamps();
            // Indexes
            $table->index('type');
            $table->index('verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
