<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Thread;
use App\Models\ThreadMember;
use App\Models\Message;
use App\Models\Forum;
use App\Models\ForumMember;
use App\Models\ForumPost;
use App\Models\ForumComment;

class MessagingForumsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some users
        $users = User::take(5)->get();

        if ($users->count() < 2) {
            $this->command->warn('Not enough users to seed messaging and forums. Please create users first.');
            return;
        }

        // Create Threads
        $thread = Thread::create([
            'title' => 'General Discussion',
            'thread_type' => 'group',
            'created_by' => $users->first()->id,
            'member_count' => 3,
        ]);

        // Add members to a thread
        foreach ($users->take(3) as $index => $user) {
            ThreadMember::create([
                'thread_id' => $thread->id,
                'user_id' => $user->id,
                'role' => $index === 0 ? 'owner' : 'member',
                'status' => 'active',
            ]);
        }

        // Create Messages
        Message::create([
            'thread_id' => $thread->id,
            'sender_id' => $users->first()->id,
            'content' => 'Hello everyone! Welcome to the group.',
            'status' => 'sent',
        ]);

        Message::create([
            'thread_id' => $thread->id,
            'sender_id' => $users->skip(1)->first()->id,
            'content' => 'Thanks for creating this group!',
            'status' => 'sent',
        ]);

        // Create Forum
        $forum = Forum::create([
            'title' => 'General Discussions',
            'slug' => 'general-discussions',
            'description' => 'A place for general discussions about everything',
            'public' => true,
            'created_by' => $users->first()->id,
            'is_active' => true,
            'allow_posts' => true,
            'member_count' => 5,
        ]);

        // Add forum members
        foreach ($users as $index => $user) {
            ForumMember::create([
                'forum_id' => $forum->id,
                'user_id' => $user->id,
                'role' => $index === 0 ? 'admin' : 'member',
                'can_post' => true,
                'can_comment' => true,
                'can_moderate' => $index === 0,
            ]);
        }

        // Create Forum Post
        $post = ForumPost::create([
            'forum_id' => $forum->id,
            'author_id' => $users->first()->id,
            'title' => 'Welcome to our forum!',
            'slug' => 'welcome-to-our-forum',
            'body' => 'This is the first post in our new forum. Feel free to introduce yourself!',
            'status' => 'published',
            'comment_count' => 2,
        ]);

        // Create Forum Comments
        ForumComment::create([
            'forum_post_id' => $post->id,
            'author_id' => $users->skip(1)->first()->id,
            'content' => 'Great to be here! Looking forward to the discussions.',
            'status' => 'published',
        ]);

        ForumComment::create([
            'forum_post_id' => $post->id,
            'author_id' => $users->skip(2)->first()->id,
            'content' => 'Thanks for setting this up!',
            'status' => 'published',
        ]);

        $this->command->info('Messaging and Forums data seeded successfully!');
    }
}
