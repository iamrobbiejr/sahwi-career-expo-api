<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class ForumComment extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'forum_post_id',
        'author_id',
        'content',
        'status',
        'parent_comment_id',
        'depth',
        'thread_path',
        'attachments',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'mentions',
        'like_count',
        'reply_count',
        'edited_at',
        'edited_by',
        'original_content',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'attachments' => 'array',
        'mentions' => 'array',
        'approved_at' => 'datetime',
        'edited_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($comment) {
            if ($comment->parent_comment_id) {
                $parent = static::find($comment->parent_comment_id);
                $comment->depth = $parent->depth + 1;
                $comment->thread_path = $parent->thread_path . '/' . $parent->id;
            } else {
                $comment->depth = 0;
                $comment->thread_path = '';
            }
        });
    }

    /**
     * Get the post.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'forum_post_id');
    }

    /**
     * Get the author.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get parent comment.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ForumComment::class, 'parent_comment_id');
    }

    /**
     * Get child comments.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ForumComment::class, 'parent_comment_id');
    }

    /**
     * Scope to published comments.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Check if comment is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
