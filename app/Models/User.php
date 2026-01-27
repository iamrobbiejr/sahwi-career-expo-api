<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    /**
     * The attributes that are mass-assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
         'role',
        'verified',
        'verification_submitted_at',
        'verification_reviewed_at',
        'interested_university_id',
        'expert_field',
        'current_school_name',
        'current_grade',
        'dob',
        'bio',
        'organisation_id',
        'title',
        'whatsapp_number',
        'interested_area',
        'interested_course',
        'avatar_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
             'verified' => 'boolean',
        'dob' => 'date',
        'verification_submitted_at' => 'datetime',
        'verification_reviewed_at' => 'datetime',
        ];
    }

     /**
     * Relationships
     */

    // User belongs to a university they are interested in
    public function interestedUniversity(): BelongsTo
    {
        return $this->belongsTo(University::class, 'interested_university_id');
    }

    // User belongs to an organization (company/university/etc.)
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organisation_id');
    }

    /**
     * Role helpers (optional but recommended)
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isProfessional(): bool
    {
        return in_array($this->role, ['professional', 'company_rep']);
    }

    /**
     * Verification helpers
     */
    public function isVerified(): bool
    {
        return $this->verified === true;
    }

    /**
     * Get user's threads.
     */
    public function threads(): BelongsToMany
    {
        return $this->belongsToMany(Thread::class, 'thread_members')
            ->withPivot(['role', 'status', 'last_read_at', 'unread_count'])
            ->withTimestamps();
    }

    /**
     * Get user's sent messages.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Get user's forum memberships.
     */
    public function forumMemberships(): HasMany
    {
        return $this->hasMany(ForumMember::class);
    }

    /**
     * Get forums user is a member of.
     */
    public function forums(): BelongsToMany
    {
        return $this->belongsToMany(Forum::class, 'forum_members')
            ->withPivot(['role', 'status', 'can_post', 'can_comment', 'can_moderate'])
            ->withTimestamps();
    }

    /**
     * Get user's forum posts.
     */
    public function forumPosts(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'author_id');
    }

    /**
     * Get user's forum comments.
     */
    public function forumComments(): HasMany
    {
        return $this->hasMany(ForumComment::class, 'author_id');
    }

    /**
     * Get user's created threads.
     */
    public function createdThreads(): HasMany
    {
        return $this->hasMany(Thread::class, 'created_by');
    }

    /**
     * Get user's created forums.
     */
    public function createdForums(): HasMany
    {
        return $this->hasMany(Forum::class, 'created_by');
    }

    public function eventRegistrations()
    {
        return $this->hasMany(EventRegistration::class);
    }


}
