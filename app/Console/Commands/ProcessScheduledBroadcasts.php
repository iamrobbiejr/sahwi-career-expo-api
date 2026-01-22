<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEmailBroadcastJob;
use App\Models\EmailBroadcast;
use Illuminate\Console\Command;

class ProcessScheduledBroadcasts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'broadcasts:process-scheduled';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled email broadcasts';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $broadcasts = EmailBroadcast::scheduled()->get();

        foreach ($broadcasts as $broadcast) {
            $broadcast->update(['status' => 'queued']);
            ProcessEmailBroadcastJob::dispatch($broadcast)->onQueue('broadcasts');

            $this->info("Queued broadcast #{$broadcast->id}");
        }

        $this->info("Processed {$broadcasts->count()} scheduled broadcasts");
    }
}
