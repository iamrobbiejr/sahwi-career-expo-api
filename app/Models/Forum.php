<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Forum extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'slug',
        'description',
        'public',
        'moderation_policy',
        'is_active',
        'allow_posts',
        'require_approval',
        'icon',
        'banner_image',
        'display_order',
        'parent_forum_id',
        'created_by',
        'moderator_ids',
        'post_count',
        'comment_count',
        'member_count',
        'last_post_at',
        'categories',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'public' => 'boolean',
        'moderation_policy' => 'array',
        'is_active' => 'boolean',
        'allow_posts' => 'boolean',
        'require_approval' => 'boolean',
        'moderator_ids' => 'array',
        'last_post_at' => 'datetime',
        'categories' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($forum) {
            if (empty($forum->slug)) {
                $forum->slug = Str::slug($forum->title);
            }
        });
    }

    /**
     * Get the creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get parent forum.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Forum::class, 'parent_forum_id');
    }

    /**
     * Get sub-forums.
     */
    public function subForums(): HasMany
    {
        return $this->hasMany(Forum::class, 'parent_forum_id');
    }

    /**
     * Get forum posts.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(ForumPost::class);
    }

    /**
     * Get forum members.
     */
    public function members(): HasMany
    {
        return $this->hasMany(ForumMember::class);
    }

    /**
     * Scope to active forums.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to public forums.
     */
    public function scopePublic($query)
    {
        return $query->where('public', true);
    }

    /**
     * Check if user is moderator.
     */
    public function isModerator(int $userId): bool
    {
        return in_array($userId, $this->moderator_ids ?? []) ||
            $this->created_by === $userId;
    }

    /**
     * Check if user is member.
     */
    public function hasMember(int $userId): bool
    {
        return $this->members()->where('user_id', $userId)->exists();
    }
}
