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
        Schema::table('users', function (Blueprint $table) {
             $table->enum('role', ['admin', 'student', 'professional', 'company_rep', 'university']);
            $table->boolean('verified')->default(false);
            $table->timestamp('verification_submitted_at')->nullable();
            $table->timestamp('verification_reviewed_at')->nullable();
            $table->unsignedBigInteger('interested_university_id')->nullable();
            $table->string('expert_field')->nullable();

            // Broken-down profile_meta fields
            $table->string('current_school_name')->nullable();
            $table->string('current_grade')->nullable();
            $table->date('dob')->nullable();
            $table->text('bio')->nullable();
            $table->unsignedBigInteger('organisation_id')->nullable(); // Foreign key reference to organizations.id
            $table->string('title')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('interested_area')->nullable();
            $table->string('interested_course')->nullable();
            $table->string('avatar_url')->nullable();
            // Indexes for better performance
            $table->index('role');
            $table->index('verified');
            $table->foreign('interested_university_id')->references('id')->on('universities')->onDelete('set null');
            $table->foreign('organisation_id')->references('id')->on('organizations')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'verified', 'verification_submitted_at', 'verification_reviewed_at', 'interested_university_id', 'expert_field', 'current_school_name', 'current_grade', 'dob', 'bio', 'organisation_id', 'title', 'whatsapp_number', 'interested_area', 'interested_course', 'avatar_url']);
        });
    }
};
