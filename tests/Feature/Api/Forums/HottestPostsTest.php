<?php

namespace Tests\Feature\Api\Forums;

use App\Models\Forum;
use App\Models\ForumPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HottestPostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_retrieve_hottest_posts()
    {
        $user = User::factory()->create();
        $forum = Forum::factory()->create(['public' => true]);

        // Post 1: 10 views, 5 comments, 2 likes = 17 score
        $post1 = ForumPost::factory()->create([
            'forum_id' => $forum->id,
            'author_id' => $user->id,
            'title' => 'Post 1',
            'body' => 'Body 1',
            'status' => 'published',
            'view_count' => 10,
            'comment_count' => 5,
            'like_count' => 2,
        ]);

        // Post 2: 100 views, 0 comments, 0 likes = 100 score (Hottest)
        $post2 = ForumPost::factory()->create([
            'forum_id' => $forum->id,
            'author_id' => $user->id,
            'title' => 'Post 2',
            'body' => 'Body 2',
            'status' => 'published',
            'view_count' => 100,
            'comment_count' => 0,
            'like_count' => 0,
        ]);

        // Post 3: 5 views, 0 comments, 0 likes = 5 score (Not returned)
        $post3 = ForumPost::factory()->create([
            'forum_id' => $forum->id,
            'author_id' => $user->id,
            'title' => 'Post 3',
            'body' => 'Body 3',
            'status' => 'published',
            'view_count' => 5,
            'comment_count' => 0,
            'like_count' => 0,
        ]);

        // Post 4: 20 views, 10 comments, 10 likes = 40 score (2nd Hottest)
        $post4 = ForumPost::factory()->create([
            'forum_id' => $forum->id,
            'author_id' => $user->id,
            'title' => 'Post 4',
            'body' => 'Body 4',
            'status' => 'published',
            'view_count' => 20,
            'comment_count' => 10,
            'like_count' => 10,
        ]);

        // Post 5: 30 views, 0 comments, 0 likes = 30 score (3rd Hottest)
        $post5 = ForumPost::factory()->create([
            'forum_id' => $forum->id,
            'author_id' => $user->id,
            'title' => 'Post 5',
            'body' => 'Body 5',
            'status' => 'published',
            'view_count' => 30,
            'comment_count' => 0,
            'like_count' => 0,
        ]);

        $response = $this->getJson('/api/v1/forums/hottest-posts');

        $response->assertStatus(200)
            ->assertJsonCount(3);

        $data = $response->json();

        // Expected order: Post 2 (100), Post 4 (40), Post 5 (30)
        $this->assertEquals($post2->id, $data[0]['id']);
        $this->assertEquals($post4->id, $data[1]['id']);
        $this->assertEquals($post5->id, $data[2]['id']);
    }
}
