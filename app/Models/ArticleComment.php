<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class ArticleComment extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = [
        'article_id',
        'author_id',
        'parent_comment_id',
        'content',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the article this comment belongs to
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Get the author of the comment
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the parent comment (for threaded comments)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ArticleComment::class, 'parent_comment_id');
    }

    /**
     * Get all replies to this comment
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ArticleComment::class, 'parent_comment_id');
    }

    /**
     * Get visible replies
     */
    public function visibleReplies(): HasMany
    {
        return $this->hasMany(ArticleComment::class, 'parent_comment_id')
            ->where('status', 'visible');
    }

    /**
     * Scope to get only visible comments
     */
    public function scopeVisible($query)
    {
        return $query->where('status', 'visible');
    }

    /**
     * Scope to get root level comments (no parent)
     */
    public function scopeRootLevel($query)
    {
        return $query->whereNull('parent_comment_id');
    }

    /**
     * Check if comment is a reply
     */
    public function isReply(): bool
    {
        return !is_null($this->parent_comment_id);
    }
}
