<?php

namespace App\Jobs;

use App\Models\EmailBroadcast;
use App\Models\EmailBroadcastRecipient;
use App\Models\User;
use App\Notifications\BroadcastCompletedNotification;
use App\Notifications\BroadcastFailedNotification;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessEmailBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 3600; // 1 hour

    /**
     * Create a new job instance.
     */
    public function __construct(
        public EmailBroadcast $broadcast
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Update status to processing
            $this->broadcast->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            $this->broadcast->log('info', 'Broadcast processing started', [], 'started');

            // Get recipients based on audience type
            $recipients = $this->getRecipients();

            if ($recipients->isEmpty()) {
                throw new Exception('No recipients found for this broadcast');
            }

            // Create recipient records
            $this->createRecipientRecords($recipients);

            // Update total recipients count
            $this->broadcast->update([
                'total_recipients' => $recipients->count(),
            ]);

            $this->broadcast->log('info', "Found {$recipients->count()} recipients", [
                'count' => $recipients->count(),
            ], 'recipients_found');

            // Dispatch individual email jobs in batches
            $this->dispatchEmailJobs();

            // Update status to completed
            $this->broadcast->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $this->broadcast->log('info', 'Broadcast processing completed', [], 'completed');

            // Send success notification to sender
            $this->sendSuccessNotification();

        } catch (Exception $e) {
            $this->handleFailure($e);
        }
    }

    /**
     * Get recipients based on an audience type.
     * @throws Exception
     */
    protected function getRecipients()
    {
        $query = User::query();

        switch ($this->broadcast->audience_type) {
            case 'all_users':
                // All registered users
                $query->whereNotNull('email_verified_at');
                break;

            case 'university_interested':
                // Users interested in specific university
                if (!$this->broadcast->target_university_id) {
                    throw new Exception('Target university not specified');
                }

                $query->whereHas('interestedUniversities', function ($q) {
                    $q->where('university_id', $this->broadcast->target_university_id);
                });
                break;

            case 'event_registered':
                // Users registered for specific event
                if (!$this->broadcast->target_event_id) {
                    throw new Exception('Target event not specified');
                }

                $query->whereHas('eventRegistrations', function ($q) {
                    $q->where('event_id', $this->broadcast->target_event_id);
                });
                break;

            case 'custom':
                // Custom list of user IDs
                if (empty($this->broadcast->custom_user_ids)) {
                    throw new Exception('Custom user IDs not specified');
                }

                $query->whereIn('id', $this->broadcast->custom_user_ids);
                break;

            default:
                throw new Exception('Invalid audience type');
        }

        // Apply additional filters if specified
        if ($this->broadcast->filters) {
            $this->applyFilters($query, $this->broadcast->filters);
        }

        return $query->select('id', 'name', 'email')->get();
    }

    /**
     * Apply additional filters to query.
     */
    protected function applyFilters($query, array $filters): void
    {
        if (isset($filters['user_type'])) {
            $query->where('user_type', $filters['user_type']);
        }

        if (isset($filters['created_after'])) {
            $query->where('created_at', '>=', $filters['created_after']);
        }

        if (isset($filters['created_before'])) {
            $query->where('created_at', '<=', $filters['created_before']);
        }

        // Add more filters as needed
    }

    /**
     * Create recipient records in bulk.
     */
    protected function createRecipientRecords($recipients): void
    {
        $recipientData = $recipients->map(function ($user) {
            return [
                'email_broadcast_id' => $this->broadcast->id,
                'user_id' => $user->id,
                'email' => $user->email,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        // Insert in chunks to avoid memory issues
        foreach (array_chunk($recipientData, 1000) as $chunk) {
            DB::table('email_broadcast_recipients')->insert($chunk);
        }
    }

    /**
     * Dispatch individual email jobs in batches.
     */
    protected function dispatchEmailJobs(): void
    {
        $batchSize = 100;

        EmailBroadcastRecipient::where('email_broadcast_id', $this->broadcast->id)
            ->where('status', 'pending')
            ->chunk($batchSize, function ($recipients) {
                foreach ($recipients as $recipient) {
                    SendBroadcastEmailJob::dispatch($this->broadcast, $recipient)
                        ->onQueue('emails');
                }
            });
    }

    /**
     * Send success notification to sender.
     */
    protected function sendSuccessNotification(): void
    {
        try {
            $this->broadcast->sender->notify(
                new BroadcastCompletedNotification($this->broadcast)
            );
        } catch (Exception $e) {
            Log::channel('email_broadcast')->error('Failed to send success notification', [
                'broadcast_id' => $this->broadcast->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle job failure.
     */
    protected function handleFailure(Exception $e): void
    {
        $this->broadcast->update([
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'completed_at' => now(),
        ]);

        $this->broadcast->log('error', 'Broadcast processing failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 'failed');

        Log::channel('email_broadcast')->error('Broadcast processing failed', [
            'broadcast_id' => $this->broadcast->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Send failure notification to sender
        try {
            $this->broadcast->sender->notify(
                new BroadcastFailedNotification($this->broadcast, $e->getMessage())
            );
        } catch (Exception $notificationError) {
            Log::channel('email_broadcast')->error('Failed to send failure notification', [
                'broadcast_id' => $this->broadcast->id,
                'error' => $notificationError->getMessage(),
            ]);
        }
    }

    /**
     * Handle job failure at queue level.
     */
    public function failed(Exception $exception): void
    {
        $this->handleFailure($exception);
    }
}
