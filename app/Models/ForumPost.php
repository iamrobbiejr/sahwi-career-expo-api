<?php

namespace App\Models;

use App\Services\RewardService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;
use Throwable;

class ForumPost extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'forum_id',
        'author_id',
        'title',
        'slug',
        'body',
        'status',
        'is_pinned',
        'is_locked',
        'allow_comments',
        'tags',
        'category',
        'attachments',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'view_count',
        'comment_count',
        'like_count',
        'last_activity_at',
        'edited_at',
        'edited_by',
        'meta_title',
        'meta_description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_pinned' => 'boolean',
        'is_locked' => 'boolean',
        'allow_comments' => 'boolean',
        'tags' => 'array',
        'attachments' => 'array',
        'approved_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'edited_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($post) {
            if (empty($post->slug)) {
                $post->slug = Str::slug($post->title);
            }
            $post->last_activity_at = now();
        });
    }

    /**
     * Get the forum.
     */
    public function forum(): BelongsTo
    {
        return $this->belongsTo(Forum::class);
    }

    /**
     * Get the author.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the approver.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the editor.
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    /**
     * Get comments.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ForumComment::class);
    }

    /**
     * Get root comments (no parent).
     */
    public function rootComments(): HasMany
    {
        return $this->comments()->whereNull('parent_comment_id');
    }

    /**
     * Scope to published posts.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope to pinned posts.
     */
    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    /**
     * Check if post is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Check if post is locked.
     */
    public function isLocked(): bool
    {
        return $this->is_locked;
    }

    /**
     * Increment view count.
     */
    public function incrementViews(): void
    {
        // Increment view count
        $this->increment('view_count');

        // Reward author on hitting view milestones
        try {
            $this->refresh();
            $milestones = config('rewards.view_milestones', []);
            if (is_array($milestones) && in_array((int)$this->view_count, $milestones, true)) {
                $author = $this->author;
                if ($author) {
                    // Avoid duplicate awards for the same milestone
                    $exists = UserReward::where('user_id', $author->id)
                        ->where('action', 'forum_post_viewed')
                        ->where('meta->post_id', $this->id)
                        ->where('meta->milestone', (int)$this->view_count)
                        ->exists();

                    if (!$exists) {
                        app(RewardService::class)->awardFor($author, 'forum_post_viewed', [
                            'post_id' => $this->id,
                            'milestone' => (int)$this->view_count,
                        ]);
                    }
                }
            }
        } catch (Throwable $e) {
            // Never fail due to rewards
        }
    }
}
