<?php

namespace Tests\Feature\Rewards;

use App\Models\User;
use App\Models\UserReward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardsHistoryEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_rewards_history_returns_paginated_entries_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        // Seed some rewards
        UserReward::create([
            'user_id' => $user->id,
            'action' => 'daily_login',
            'points' => (int)config('rewards.points.daily_login'),
            'meta' => null,
            'awarded_at' => now(),
            'award_date' => now()->toDateString(),
        ]);

        UserReward::create([
            'user_id' => $user->id,
            'action' => 'forum_post_create',
            'points' => (int)config('rewards.points.forum_post_create'),
            'meta' => ['post_id' => 1],
            'awarded_at' => now()->subDay(),
            'award_date' => now()->subDay()->toDateString(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/me/rewards/history');

        $response->assertOk()
            ->assertJsonStructure([
                'current_page',
                'data',
                'first_page_url',
                'from',
                'last_page',
                'last_page_url',
                'links',
                'next_page_url',
                'path',
                'per_page',
                'prev_page_url',
                'to',
                'total',
            ])
            ->assertJsonCount(2, 'data');
    }
}
