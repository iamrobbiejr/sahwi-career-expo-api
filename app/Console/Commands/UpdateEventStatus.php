<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class UpdateEventStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:update-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically update event statuses based on their start and end dates';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $now = Carbon::now();

        // Mark events as 'active' if they have started but not yet ended
        $activated = Event::where('status', 'draft')
            ->where('start_date', '<=', $now)
            ->where(function ($query) use ($now) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>', $now);
            })
            ->update(['status' => 'active']);

        $this->info("Activated {$activated} event(s).");

        // Mark events as 'completed' if their end date has passed
        $completed = Event::whereIn('status', ['active', 'draft'])
            ->whereNotNull('end_date')
            ->where('end_date', '<', $now)
            ->update(['status' => 'completed']);

        $this->info("Marked {$completed} event(s) as completed.");

        $this->info('Event status update finished.');
    }
}
