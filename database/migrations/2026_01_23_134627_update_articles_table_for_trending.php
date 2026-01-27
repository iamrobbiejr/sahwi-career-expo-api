<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Engagement metrics
            $table->integer('views_count')->default(0)->after('body');
            $table->integer('likes_count')->default(0)->after('views_count');
            $table->integer('comments_count')->default(0)->after('likes_count');
            $table->integer('shares_count')->default(0)->after('comments_count');
            $table->integer('bookmarks_count')->default(0)->after('shares_count');

            // Trending calculation
            $table->decimal('trending_score', 10, 2)->default(0)->after('bookmarks_count');
            $table->timestamp('last_trending_calculation')->nullable()->after('trending_score');
            $table->timestamp('published_at')->nullable()->after('published');

            // Optimization indexes
            $table->index('trending_score');
            $table->index('views_count');
            $table->index(['created_at', 'trending_score']);
            $table->index(['published_at', 'trending_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['trending_score']);
            $table->dropIndex(['views_count']);
            $table->dropIndex(['created_at', 'trending_score']);
            $table->dropIndex(['published_at', 'trending_score']);

            $table->dropColumn([
                'views_count',
                'likes_count',
                'comments_count',
                'shares_count',
                'bookmarks_count',
                'trending_score',
                'last_trending_calculation',
                'published_at',
            ]);
        });
    }
};
