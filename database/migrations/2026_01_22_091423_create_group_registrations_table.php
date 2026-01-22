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
        Schema::create('group_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('registered_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('organization_id')->nullable()->constrained()->onDelete('set null');

            $table->string('group_name')->nullable();
            $table->integer('total_members')->default(0);
            $table->integer('confirmed_members')->default(0);

            $table->timestamps();

            $table->index(['event_id', 'registered_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_registrations');
    }
};
