<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserReward;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RewardService
{
    /**
     * Update the user's daily activity streak.
     * - If first activity, start at 1
     * - If last activity was yesterday, increment
     * - If last activity was today, keep as is
     * - Otherwise, reset to 1
     */
    public function touchStreak(User $user, ?Carbon $activityDate = null): void
    {
        $today = ($activityDate ?? now())->startOfDay();
        $last = $user->streak_last_date ? Carbon::parse($user->streak_last_date)->startOfDay() : null;

        if ($last === null) {
            $user->streak_days = 1;
            $user->streak_last_date = $today;
            $user->save();
            return;
        }

        if ($last->equalTo($today)) {
            // already counted for today
            return;
        }

        if ($last->equalTo($today->copy()->subDay())) {
            $user->streak_days = (int)$user->streak_days + 1;
        } else {
            $user->streak_days = 1;
        }

        $user->streak_last_date = $today;
        $user->save();
    }

    /**
     * Award reputation points to a user.
     */
    public function awardPoints(User $user, int $points): void
    {
        if ($points === 0) {
            return;
        }
        $user->reputation_points = (int)$user->reputation_points + $points;
        $user->save();
    }

    /**
     * Convenience to award activity: update streak and points.
     */
    public function awardActivity(User $user, int $points, ?Carbon $activityDate = null): void
    {
        $this->touchStreak($user, $activityDate);
        $this->awardPoints($user, $points);
    }

    /**
     * Award points for a configured action and optionally touch streak.
     * Records a ledger entry and prevents duplicates based on config policies.
     */
    public function awardFor(User $user, string $action, array $meta = [], ?Carbon $when = null): void
    {
        $when = $when ? $when->copy() : now();
        $points = (int)config("rewards.points.$action", 0);
        $touch = (bool)config("rewards.touch_streak.$action", false);
        $dedup = (string)config("rewards.dedup.$action", 'allow_multiple');
        $awardDate = $when->copy()->startOfDay()->toDateString();

        // De-duplication checks
        if ($dedup === 'one_time') {
            $exists = UserReward::where('user_id', $user->id)
                ->where('action', $action)
                ->exists();
            if ($exists) {
                return;
            }
        } elseif ($dedup === 'once_per_day') {
            $exists = UserReward::where('user_id', $user->id)
                ->where('action', $action)
                ->whereDate('award_date', $awardDate)
                ->exists();
            if ($exists) {
                return;
            }
        }

        if ($touch) {
            $this->touchStreak($user, $when);
        }

        if ($points === 0) {
            // Nothing to record if no points configured and we already handled streak
            return;
        }

        try {
            DB::transaction(function () use ($user, $action, $points, $meta, $when, $awardDate) {
                // Create ledger entry
                UserReward::create([
                    'user_id' => $user->id,
                    'action' => $action,
                    'points' => $points,
                    'meta' => $meta ?: null,
                    'awarded_at' => $when,
                    'award_date' => $awardDate,
                ]);

                // Increment user's points atomically
                $user->increment('reputation_points', $points);
            });
        } catch (QueryException $e) {
            // Swallow unique constraint violation for once-per-day/one-time dedup
            if (str_contains(strtolower($e->getMessage()), 'unique') || str_contains(strtolower($e->getMessage()), 'duplicate')) {
                return;
            }
            throw $e;
        }
    }
}
