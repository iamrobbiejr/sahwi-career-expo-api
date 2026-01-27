<?php

namespace App\Console\Commands;

use App\Services\TrendingArticleService;
use Illuminate\Console\Command;

class UpdateTrendingScores extends Command
{
    protected $signature = 'articles:update-trending-scores';
    protected $description = 'Update trending scores for all articles';

    public function handle(TrendingArticleService $trendingService): int
    {
        $this->info('Updating trending scores...');

        $updated = $trendingService->updateAllTrendingScores();

        $this->info("Updated {$updated} articles.");

        return Command::SUCCESS;
    }
}
