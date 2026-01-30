<?php

namespace Tests\Feature\Rewards;

use App\Models\User;
use App\Services\RewardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyLoginRewardTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_login_awarded_once_per_day_and_increments_next_day(): void
    {
        $user = User::factory()->create();

        $service = app(RewardService::class);

        $points = (int)config('rewards.points.daily_login');

        // First award today
        $service->awardFor($user, 'daily_login', [], Carbon::now());
        $this->assertDatabaseCount('user_rewards', 1);
        $this->assertEquals($points, $user->fresh()->reputation_points);

        // Duplicate same day should not add more
        $service->awardFor($user, 'daily_login', [], Carbon::now());
        $this->assertDatabaseCount('user_rewards', 1);
        $this->assertEquals($points, $user->fresh()->reputation_points);

        // Next day should add another
        $tomorrow = Carbon::now()->addDay();
        $service->awardFor($user, 'daily_login', [], $tomorrow);

        $this->assertDatabaseCount('user_rewards', 2);
        $this->assertEquals($points * 2, $user->fresh()->reputation_points);
    }
}
