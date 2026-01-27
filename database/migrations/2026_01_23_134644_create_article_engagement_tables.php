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
        Schema::create('article_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('referrer')->nullable();
            $table->integer('duration_seconds')->nullable(); // Time spent reading
            $table->timestamp('viewed_at');

            $table->index('article_id');
            $table->index('user_id');
            $table->index('viewed_at');
            $table->index(['article_id', 'viewed_at']);
        });

        Schema::create('article_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['article_id', 'user_id']);
            $table->index('article_id');
            $table->index('created_at');
        });

        Schema::create('article_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('collection')->nullable(); // For organizing bookmarks
            $table->timestamps();

            $table->unique(['article_id', 'user_id']);
            $table->index('article_id');
            $table->index('user_id');
        });

        Schema::create('article_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('platform'); // 'twitter', 'linkedin', 'facebook', 'whatsapp', 'email'
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index('article_id');
            $table->index('platform');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_shares');
        Schema::dropIfExists('article_bookmarks');
        Schema::dropIfExists('article_likes');
        Schema::dropIfExists('article_views');
    }
};
