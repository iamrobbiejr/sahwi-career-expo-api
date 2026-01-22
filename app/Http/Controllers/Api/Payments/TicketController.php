<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService
    ) {}

    public function show(Ticket $ticket): JsonResponse
    {
        $registration = $ticket->registration;

        // Authorization
        if ($registration->user_id !== auth()->id() &&
            $registration->registered_by !== auth()->id() &&
            !auth()->user()->hasRole('admin')) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $ticket->load(['registration.event', 'registration.user', 'payment']);

        return response()->json([
            'ticket' => $ticket,
        ]);
    }

    public function download(Ticket $ticket)
    {
        $registration = $ticket->registration;

        // Authorization
        if ($registration->user_id !== auth()->id() &&
            $registration->registered_by !== auth()->id() &&
            !auth()->user()->hasRole('admin')) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        if (!$ticket->pdf_path || !Storage::exists($ticket->pdf_path)) {
            // Regenerate PDF if missing
            $this->ticketService->generatePDF($ticket);
        }

        return Storage::download($ticket->pdf_path, "ticket-{$ticket->ticket_number}.pdf");
    }

    public function resend(Request $request, Ticket $ticket): JsonResponse
    {
        $registration = $ticket->registration;

        // Authorization
        if ($registration->user_id !== auth()->id() &&
            $registration->registered_by !== auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $this->ticketService->emailTicket($ticket);

        return response()->json([
            'message' => 'Ticket sent successfully',
        ]);
    }

    public function myTickets(Request $request): JsonResponse
    {
        $tickets = Ticket::whereHas('registration', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id)
                ->orWhere('registered_by', $request->user()->id);
        })
            ->with(['registration.event'])
            ->when($request->has('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($tickets);
    }

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ticket_number' => 'required|string|exists:tickets,ticket_number',
        ]);

        $ticket = Ticket::where('ticket_number', $validated['ticket_number'])
            ->with(['registration.event', 'registration.user'])
            ->first();

        if (!$ticket) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid ticket',
            ], 404);
        }

        if (!$ticket->isValid()) {
            return response()->json([
                'valid' => false,
                'message' => 'Ticket is not valid',
                'status' => $ticket->status,
                'ticket' => $ticket,
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Valid ticket',
            'ticket' => $ticket,
            'already_used' => $ticket->status === 'used',
        ]);
    }

    public function checkIn(Request $request, Ticket $ticket): JsonResponse
    {
        if (!$ticket->isValid()) {
            return response()->json([
                'message' => 'Ticket is not valid for check-in',
                'status' => $ticket->status,
            ], 422);
        }

        if ($ticket->status === 'used') {
            return response()->json([
                'message' => 'Ticket already used',
                'used_at' => $ticket->used_at,
                'used_by' => $ticket->usedBy,
            ], 422);
        }

        $ticket->update([
            'status' => 'used',
            'used_at' => now(),
            'used_by' => $request->user()->id,
        ]);

        $ticket->registration->update([
            'checked_in_at' => now(),
        ]);

        return response()->json([
            'message' => 'Check-in successful',
            'ticket' => $ticket->fresh()->load(['registration.event', 'registration.user']),
        ]);
    }
}
