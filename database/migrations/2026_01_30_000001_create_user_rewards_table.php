<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');
            $table->integer('points');
            $table->json('meta')->nullable();
            $table->timestamp('awarded_at')->useCurrent();
            $table->date('award_date');
            $table->timestamps();

            // Indices for querying and de-duplication
            $table->index(['user_id', 'action', 'awarded_at']);
            // Once per day for actions that require it (enforced in code selectively)
            $table->unique(['user_id', 'action', 'award_date'], 'uniq_user_action_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_rewards');
    }
};
