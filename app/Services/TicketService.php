<?php

namespace App\Services;

use App\Mail\TicketMail;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Log;
use Milon\Barcode\DNS1D;

class TicketService
{
    public function generateTicketsForPayment(Payment $payment): void
    {
        foreach ($payment->items as $item) {
            $this->generateTicketRecord($item->registration, $payment);
        }
    }

    /**
     * Renamed from generateTicket to be more descriptive of its role.
     * This handles record creation and file generation only.
     */
    public function generateTicketRecord(EventRegistration $registration, Payment $payment = null): Ticket
    {
        $ticket = Ticket::create([
            'event_registration_id' => $registration->id,
            'payment_id' => $payment?->id,
            'ticket_number' => $registration->ticket_number,
            'status' => 'active',
        ]);

        $this->generateBarcode($ticket);
        $this->generatePDF($ticket);

        return $ticket;
    }

    public function generateBarcode(Ticket $ticket): void
    {
        $barcode = new DNS1D();
        $barcodeImage = $barcode->getBarcodePNG($ticket->ticket_number, 'C128', 3, 100);

        // Save barcode as image
        $path = "tickets/barcodes/{$ticket->ticket_number}.png";
        Storage::disk('public')->put(
            $path,
            base64_decode($barcodeImage)
        );

        $ticket->update([
            'qr_code_path' => $path,
        ]);
    }

    public function generatePDF(Ticket $ticket): void
    {
        $registration = $ticket->registration;
        $event = $registration->event;

        // Get a barcode image as base64
        $barcodeBase64 = base64_encode(Storage::disk('public')->get($ticket->qr_code_path));

        $data = [
            'ticket' => $ticket,
            'registration' => $registration,
            'event' => $event,
            'barcode' => $barcodeBase64,
        ];

        $pdf = Pdf::loadView('pdfs.ticket', $data)
            ->setPaper('a4', 'portrait');

        $path = "tickets/pdfs/{$ticket->ticket_number}.pdf";
        Storage::disk('public')->put($path, $pdf->output());

        $ticket->update([
            'pdf_path' => $path,
        ]);
    }

    public function emailTicket(Ticket $ticket): void
    {
        try {
            $registration = $ticket->registration;

            Mail::to($registration->attendee_email)
                ->send(new TicketMail($ticket));

            $ticket->update([
                'emailed_at' => now(),
                'email_attempts' => $ticket->email_attempts + 1,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to email ticket', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);

            $ticket->increment('email_attempts');
        }
    }
}
