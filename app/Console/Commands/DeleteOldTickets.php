<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DeleteOldTickets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:delete-old
                            {--dry-run : List files that would be removed without actually removing them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove QR code and PDF files for tickets belonging to completed (past) events';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $dryRun = $this->option('dry-run');

        // Target tickets whose event has already ended (status = completed)
        // and that still have QR code or PDF files stored
        $tickets = Ticket::whereHas('registration.event', function ($query) {
            $query->where('status', 'completed');
        })
            ->where(function ($query) {
                $query->whereNotNull('qr_code_path')
                    ->orWhereNotNull('pdf_path');
            })
            ->with('registration.event')
            ->get();

        if ($tickets->isEmpty()) {
            $this->info('No ticket files found for completed events.');
            return;
        }

        if ($dryRun) {
            $this->warn("[Dry Run] {$tickets->count()} ticket file(s) would be removed:");
            $this->table(
                ['Ticket ID', 'Ticket #', 'Event', 'QR Code', 'PDF'],
                $tickets->map(fn($t) => [
                    $t->id,
                    $t->ticket_number,
                    $t->registration->event->name ?? '—',
                    $t->qr_code_path ?? '—',
                    $t->pdf_path ?? '—',
                ])->toArray()
            );
            return;
        }

        $filesRemoved = 0;
        $ticketsProcessed = 0;

        foreach ($tickets as $ticket) {
            $cleared = [];

            foreach (['qr_code_path', 'pdf_path'] as $fileField) {
                if (!empty($ticket->{$fileField})) {
                    $path = ltrim($ticket->{$fileField}, '/');

                    if (Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                        $filesRemoved++;
                    }

                    // Null out the path so we don't attempt it again next run
                    $cleared[$fileField] = null;
                }
            }

            if (!empty($cleared)) {
                $ticket->update($cleared);
                $ticketsProcessed++;
            }
        }

        $this->info("Cleaned up {$filesRemoved} file(s) across {$ticketsProcessed} ticket(s) for completed events.");
    }
}
