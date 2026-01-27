<?php

namespace App\Console\Commands;

use App\Services\TrendingArticleService;
use Illuminate\Console\Command;

class WarmArticleCache extends Command
{
    protected $signature = 'articles:warm-cache';
    protected $description = 'Pre-warm article caches';

    public function handle(TrendingArticleService $trendingService): int
    {
        $this->info('Warming article caches...');

        // Warm trending caches for different periods
        $periods = ['1h', '24h', '7d'];

        foreach ($periods as $period) {
            $this->info("Warming cache for period: {$period}");
            $trendingService->getTrending(10, $period);
        }

        // Warm trending topics cache
        $this->info('Warming trending topics cache');
        $trendingService->getTrendingTopics(10);

        $this->info('Cache warming complete!');

        return Command::SUCCESS;
    }
}
