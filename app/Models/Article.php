<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    protected $fillable = [
        'title',
        'body',
        'author_id',
        'category_id',
        'published',
        'published_at',
        'allow_comments',
        'tags',
        'views_count',
        'likes_count',
        'comments_count',
        'shares_count',
        'bookmarks_count',
        'trending_score',
        'last_trending_calculation',
    ];

    protected $casts = [
        'published' => 'boolean',
        'allow_comments' => 'boolean',
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'published_at' => 'datetime',
        'last_trending_calculation' => 'datetime',
        'trending_score' => 'decimal:2',
    ];

    /**
     * Get the author of the article
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get all comments for the article
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ArticleComment::class);
    }

    /**
     * Get only visible comments
     */
    public function visibleComments(): HasMany
    {
        return $this->hasMany(ArticleComment::class)
            ->where('status', 'visible')
            ->whereNull('parent_comment_id'); // Only root level comments
    }

    /**
     * Engagement relationships
     */
    public function views(): HasMany
    {
        return $this->hasMany(ArticleView::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(ArticleLike::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(ArticleBookmark::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(ArticleShare::class);
    }

    /**
     * Check if user has liked the article
     */
    public function hasLiked(?User $user): bool
    {
        if (!$user) return false;
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    /**
     * Toggle like for a user
     */
    public function toggleLike(User $user): bool
    {
        $like = $this->likes()->where('user_id', $user->id)->first();

        if ($like) {
            $like->delete();
            $this->decrement('likes_count');
            return false;
        }

        $this->likes()->create([
            'user_id' => $user->id,
        ]);
        $this->increment('likes_count');
        return true;
    }

    /**
     * Check if user has bookmarked the article
     */
    public function hasBookmarked(?User $user): bool
    {
        if (!$user) return false;
        return $this->bookmarks()->where('user_id', $user->id)->exists();
    }

    /**
     * Toggle bookmark for a user
     */
    public function toggleBookmark(User $user, ?string $collection = null): bool
    {
        $bookmark = $this->bookmarks()->where('user_id', $user->id)->first();

        if ($bookmark) {
            $bookmark->delete();
            $this->decrement('bookmarks_count');
            return false;
        }

        $this->bookmarks()->create([
            'user_id' => $user->id,
            'collection' => $collection,
        ]);
        $this->increment('bookmarks_count');
        return true;
    }

    /**
     * Record a share
     */
    public function recordShare(string $platform, ?User $user = null): void
    {
        $this->shares()->create([
            'user_id' => $user?->id,
            'platform' => $platform,
            'ip_address' => request()->ip(),
        ]);
        $this->increment('shares_count');
    }

    /**
     * Record a view
     */
    public function recordView(?User $user = null): void
    {
        $this->views()->create([
            'user_id' => $user?->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'referrer' => request()->header('referer'),
            'viewed_at' => now(),
        ]);
        $this->increment('views_count');
    }

    /**
     * Scope to get only published articles
     */
    public function scopePublished($query)
    {
        return $query->where('published', true);
    }

    /**
     * Scope to filter by tags
     */
    public function scopeWithTag($query, string $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }
}
