<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class CleanOldLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:clean
                            {--days=14 : Number of days after which log files are deleted}
                            {--dry-run : List files that would be deleted without actually deleting them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete log files under storage/logs that are older than the specified number of days';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $days = (int)$this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoff = Carbon::now()->subDays($days);

        $logPath = storage_path('logs');

        // Gather all .log files recursively, skipping the rolling laravel.log symlink/file
        $files = File::allFiles($logPath);

        $toDelete = collect($files)->filter(function ($file) use ($cutoff) {
            // Skip the generic laravel.log (no date, always current)
            if ($file->getFilename() === 'laravel.log') {
                return false;
            }

            // Delete if the file's last-modified time is older than the cutoff
            return Carbon::createFromTimestamp($file->getMTime())->lt($cutoff);
        });

        if ($toDelete->isEmpty()) {
            $this->info("No log files older than {$days} days found.");
            return;
        }

        if ($dryRun) {
            $this->warn("[Dry Run] The following {$toDelete->count()} file(s) would be deleted:");
            $this->table(
                ['File', 'Size', 'Last Modified'],
                $toDelete->map(fn($f) => [
                    str_replace($logPath . DIRECTORY_SEPARATOR, '', $f->getPathname()),
                    $this->formatBytes($f->getSize()),
                    Carbon::createFromTimestamp($f->getMTime())->toDateTimeString(),
                ])->values()->toArray()
            );
            return;
        }

        $deleted = 0;
        $totalSize = 0;

        foreach ($toDelete as $file) {
            $totalSize += $file->getSize();
            File::delete($file->getPathname());
            $this->line('  Deleted: ' . str_replace($logPath . DIRECTORY_SEPARATOR, '', $file->getPathname()));
            $deleted++;
        }

        // Remove any now-empty subdirectories
        foreach (File::directories($logPath) as $dir) {
            if (count(File::allFiles($dir)) === 0) {
                File::deleteDirectory($dir);
                $this->line('  Removed empty directory: ' . basename($dir));
            }
        }

        $this->info("Deleted {$deleted} log file(s), freed " . $this->formatBytes($totalSize) . '.');
    }

    /**
     * Format bytes into a human-readable string.
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
