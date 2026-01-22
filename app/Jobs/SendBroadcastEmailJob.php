<?php

namespace App\Jobs;

use App\Mail\BroadcastEmail;
use App\Models\EmailBroadcast;
use App\Models\EmailBroadcastRecipient;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBroadcastEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public EmailBroadcast $broadcast,
        public EmailBroadcastRecipient $recipient
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Send the email
            Mail::to($this->recipient->email)
                ->send(new BroadcastEmail($this->broadcast, $this->recipient));

            // Mark as sent
            $this->recipient->markAsSent();

            // Update broadcast sent count
            $this->broadcast->increment('sent_count');

            Log::channel('email_broadcast')->info('Email sent successfully', [
                'broadcast_id' => $this->broadcast->id,
                'recipient_id' => $this->recipient->id,
                'email' => $this->recipient->email,
            ]);

        } catch (Exception $e) {
            $this->handleFailure($e);
        }
    }

    /**
     * Handle job failure.
     */
    protected function handleFailure(Exception $e): void
    {
        // Mark recipient as failed
        $this->recipient->markAsFailed($e->getMessage());

        // Update broadcast failed count
        $this->broadcast->increment('failed_count');

        Log::channel('email_broadcast')->error('Email sending failed', [
            'broadcast_id' => $this->broadcast->id,
            'recipient_id' => $this->recipient->id,
            'email' => $this->recipient->email,
            'error' => $e->getMessage(),
            'attempt' => $this->attempts(),
        ]);

        // If this was the last attempt, log it
        if ($this->attempts() >= $this->tries) {
            $this->broadcast->log('error', "Failed to send email after {$this->tries} attempts", [
                'recipient_id' => $this->recipient->id,
                'email' => $this->recipient->email,
                'error' => $e->getMessage(),
            ], 'recipient_failed');
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
