<?php

namespace App\Services;

use App\Models\Article;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TrendingArticleService
{
    /**
     * Configuration for trending algorithm
     */
    protected array $config = [
        'weights' => [
            'views' => 1,
            'likes' => 3,
            'comments' => 5,
            'shares' => 10,
            'bookmarks' => 4,
        ],
        'decay_factor' => 1.5,
        'time_offset' => 2, // hours
        'min_views' => 10, // Minimum views to be considered trending
    ];

    /**
     * Calculate trending score for an article
     */
    public function calculateTrendingScore(Article $article): float
    {
        // Get age in hours
        $ageInHours = $article->published_at
            ? Carbon::now()->diffInHours($article->published_at)
            : Carbon::now()->diffInHours($article->created_at);

        // Minimum views threshold
        if ($article->views_count < $this->config['min_views']) {
            return 0;
        }

        // Calculate engagement score
        $engagementScore = (
            $article->views_count * $this->config['weights']['views'] +
            $article->likes_count * $this->config['weights']['likes'] +
            $article->comments_count * $this->config['weights']['comments'] +
            $article->shares_count * $this->config['weights']['shares'] +
            $article->bookmarks_count * $this->config['weights']['bookmarks']
        );

        // Apply time decay
        $timeFactor = pow(
            $ageInHours + $this->config['time_offset'],
            $this->config['decay_factor']
        );

        $trendingScore = $engagementScore / $timeFactor;

        return round($trendingScore, 2);
    }

    /**
     * Calculate velocity (engagement rate per hour)
     */
    public function calculateVelocity(Article $article, int $hoursWindow = 24): float
    {
        $cutoffTime = Carbon::now()->subHours($hoursWindow);

        // Get recent engagement counts
        $recentViews = DB::table('article_views')
            ->where('article_id', $article->id)
            ->where('viewed_at', '>=', $cutoffTime)
            ->count();

        $recentLikes = DB::table('article_likes')
            ->where('article_id', $article->id)
            ->where('created_at', '>=', $cutoffTime)
            ->count();

        $recentComments = DB::table('article_comments')
            ->where('article_id', $article->id)
            ->where('created_at', '>=', $cutoffTime)
            ->count();

        // Calculate velocity score
        $velocityScore = (
                $recentViews * 1 +
                $recentLikes * 5 +
                $recentComments * 10
            ) / $hoursWindow;

        return round($velocityScore, 2);
    }

    /**
     * Update trending scores for all articles
     */
    public function updateAllTrendingScores(): int
    {
        $updated = 0;

        // Only update articles from last 30 days
        Article::where('published_at', '>=', Carbon::now()->subDays(30))
            ->orWhere(function ($query) {
                $query->whereNull('published_at')
                    ->where('created_at', '>=', Carbon::now()->subDays(30));
            })
            ->chunk(100, function ($articles) use (&$updated) {
                foreach ($articles as $article) {
                    $score = $this->calculateTrendingScore($article);

                    $article->update([
                        'trending_score' => $score,
                        'last_trending_calculation' => now(),
                    ]);

                    $updated++;
                }
            });

        // Clear trending cache
        CacheService::clearTrendingCaches();

        return $updated;
    }

    /**
     * Get trending articles with caching
     */
    public function getTrending(
        int    $limit = 10,
        string $period = '24h',
        array  $categories = []
    ): Collection
    {
        $cacheKey = "trending_articles_{$period}_{$limit}_" . md5(json_encode($categories));

        return CacheService::tags(['articles', 'trending'])->remember(
            $cacheKey,
            now()->addMinutes(15), // Cache for 15 minutes
            function () use ($limit, $period, $categories) {
                $query = Article::with(['author']) // category might be missing in some setups, but let's check if it exists
                ->where('trending_score', '>', 0);

                // Apply time period filter
                $query->where(function ($q) use ($period) {
                    $cutoff = $this->getPeriodCutoff($period);
                    $q->where('published_at', '>=', $cutoff)
                        ->orWhere(function ($q2) use ($cutoff) {
                            $q2->whereNull('published_at')
                                ->where('created_at', '>=', $cutoff);
                        });
                });

                // Filter by categories if provided
                if (!empty($categories)) {
                    $query->whereIn('category_id', $categories);
                }

                return $query->orderBy('trending_score', 'desc')
                    ->limit($limit)
                    ->get();
            }
        );
    }

    /**
     * Get cutoff time for period
     */
    protected function getPeriodCutoff(string $period): Carbon
    {
        return match ($period) {
            '1h' => Carbon::now()->subHour(),
            '6h' => Carbon::now()->subHours(6),
            '24h' => Carbon::now()->subDay(),
            '7d' => Carbon::now()->subWeek(),
            '30d' => Carbon::now()->subMonth(),
            default => Carbon::now()->subDay(),
        };
    }

    /**
     * Get trending topics/tags
     */
    public function getTrendingTopics(int $limit = 10): array
    {
        return CacheService::tags(['articles', 'trending', 'topics'])->remember(
            "trending_topics_{$limit}",
            now()->addHours(1),
            function () use ($limit) {
                // Get articles from last 7 days
                $recentArticles = Article::where('published_at', '>=', Carbon::now()->subWeek())
                    ->orWhere(function ($query) {
                        $query->whereNull('published_at')
                            ->where('created_at', '>=', Carbon::now()->subWeek());
                    })
                    ->where('trending_score', '>', 0)
                    ->get();

                // Count tag occurrences weighted by trending score
                $tagScores = [];

                foreach ($recentArticles as $article) {
                    if ($article->tags) {
                        foreach ($article->tags as $tag) {
                            if (!isset($tagScores[$tag])) {
                                $tagScores[$tag] = 0;
                            }
                            $tagScores[$tag] += $article->trending_score;
                        }
                    }
                }

                // Sort and limit
                arsort($tagScores);
                return array_slice($tagScores, 0, $limit, true);
            }
        );
    }

    /**
     * Recalculate trending score when article gets engagement
     */
    public function recalculateScore(Article $article): void
    {
        $score = $this->calculateTrendingScore($article);

        $article->update([
            'trending_score' => $score,
            'last_trending_calculation' => now(),
        ]);

        // Invalidate cache
        CacheService::clearTrendingCaches();
    }
}
