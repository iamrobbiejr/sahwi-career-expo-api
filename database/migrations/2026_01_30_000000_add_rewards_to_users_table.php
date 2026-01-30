<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('reputation_points')->default(0)->after('avatar_url');
            $table->integer('streak_days')->default(0)->after('reputation_points');
            $table->date('streak_last_date')->nullable()->after('streak_days');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['reputation_points', 'streak_days', 'streak_last_date']);
        });
    }
};
