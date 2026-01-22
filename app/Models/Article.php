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
        'published',
        'allow_comments',
        'tags',
    ];

    protected $casts = [
        'published' => 'boolean',
        'allow_comments' => 'boolean',
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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
