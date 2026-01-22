<?php

namespace App\Http\Controllers\Api\EventRegistrations;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventRegistrationController extends Controller
{

    public function __construct(
        protected RegistrationService $registrationService
    ) {}

    public function registrations(Request $request, Event $event): JsonResponse
    {
        $registrations = $event->registrations()
            ->with(['user:id,name,email', 'registeredBy:id,name,email', 'organization'])
            ->when($request->has('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($registrations);
    }

    public function analytics(Event $event): JsonResponse
    {
        $registrations = $event->registrations()->get();

        return response()->json([
            'total_registrations' => $registrations->count(),
            'confirmed' => $registrations->where('status', 'confirmed')->count(),
            'pending' => $registrations->where('status', 'pending')->count(),
            'cancelled' => $registrations->where('status', 'cancelled')->count(),
            'by_type' => [
                'student' => $registrations->where('attendee_type', 'student')->count(),
                'professional' => $registrations->where('attendee_type', 'professional')->count(),
                'company_rep' => $registrations->where('attendee_type', 'company_rep')->count(),
            ],
            'revenue' => [
                'total_cents' => $event->payments()->where('status', 'completed')->sum('amount_cents'),
                'currency' => $event->currency,
            ],
            'capacity_utilization' => $event->capacity ?
                round(($event->registrations / $event->capacity) * 100, 2) : null,
        ]);
    }

    public function registerIndividual(Request $request, Event $event): JsonResponse
    {
        // Check if event is accepting registrations
        if ($event->status !== 'active') {
            return response()->json([
                'message' => 'Event is not accepting registrations',
            ], 422);
        }

        if ($event->registration_deadline && now()->gt($event->registration_deadline)) {
            return response()->json([
                'message' => 'Registration deadline has passed',
            ], 422);
        }

        if ($event->capacity && $event->registrations >= $event->capacity) {
            return response()->json([
                'message' => 'Event is at full capacity',
            ], 422);
        }

        // Check for duplicate registration
        $existingRegistration = EventRegistration::where('event_id', $event->id)
            ->where('user_id', $request->user()->id)
            ->whereNotIn('status', ['cancelled'])
            ->first();

        if ($existingRegistration) {
            return response()->json([
                'message' => 'You are already registered for this event',
                'registration' => $existingRegistration,
            ], 422);
        }

        $validated = $request->validate([
            'attendee_name' => 'sometimes|string|max:255',
            'attendee_email' => 'sometimes|email|max:255',
            'attendee_phone' => 'nullable|string|max:20',
            'attendee_title' => 'nullable|string|max:255',
            'attendee_organization_id' => 'nullable|exists:organizations,id',
            'special_requirements' => 'nullable|string',
            'custom_fields' => 'nullable|array',
        ]);

        $registration = $this->registrationService->registerIndividual(
            $event,
            $request->user(),
            $validated
        );

        return response()->json([
            'message' => 'Registration successful',
            'registration' => $registration->load(['event', 'user']),
            'next_step' => $event->is_paid ? 'payment' : 'complete',
        ], 201);
    }

    public function registerGroup(Request $request, Event $event): JsonResponse
    {
        if ($request->user()->role !== 'company_rep') {
            return response()->json([
                'message' => 'Only company representatives can register groups',
            ], 403);
        }

        // Validation
        if ($event->status !== 'active') {
            return response()->json([
                'message' => 'Event is not accepting registrations',
            ], 422);
        }

        $validated = $request->validate([
            'group_name' => 'nullable|string|max:255',
            'members' => 'required|array|min:1|max:50',
            'members.*.name' => 'required|string|max:255',
            'members.*.email' => 'required|email|max:255',
            'members.*.phone' => 'nullable|string|max:20',
            'members.*.title' => 'nullable|string|max:255',
            'members.*.special_requirements' => 'nullable|string',
        ]);

        // Check capacity
        $requiredSpots = count($validated['members']);
        if ($event->capacity && ($event->registrations + $requiredSpots) > $event->capacity) {
            return response()->json([
                'message' => "Not enough capacity. Only " . ($event->capacity - $event->registrations) . " spots available",
            ], 422);
        }

        // Check for duplicate emails
        $emails = collect($validated['members'])->pluck('email');
        $existingRegistrations = EventRegistration::where('event_id', $event->id)
            ->whereIn('attendee_email', $emails)
            ->whereNotIn('status', ['cancelled'])
            ->pluck('attendee_email');

        if ($existingRegistrations->isNotEmpty()) {
            return response()->json([
                'message' => 'Some members are already registered',
                'duplicate_emails' => $existingRegistrations,
            ], 422);
        }

        $groupRegistration = $this->registrationService->registerGroup(
            $event,
            $request->user(),
            $validated['members'],
            ['group_name' => $validated['group_name'] ?? null]
        );

        $groupRegistration->load(['registrations', 'organization']);

        return response()->json([
            'message' => 'Group registration successful',
            'group_registration' => $groupRegistration,
            'total_members' => $groupRegistration->total_members,
            'next_step' => $event->is_paid ? 'payment' : 'complete',
        ], 201);
    }

    public function checkStatus(Request $request, Event $event): JsonResponse
    {
        $registration = EventRegistration::where('event_id', $event->id)
            ->where('user_id', $request->user()->id)
            ->whereNotIn('status', ['cancelled'])
            ->with(['ticket', 'paymentItem.payment'])
            ->first();

        if (!$registration) {
            return response()->json([
                'registered' => false,
                'message' => 'You are not registered for this event',
            ]);
        }

        return response()->json([
            'registered' => true,
            'registration' => $registration,
            'payment_status' => $registration->paymentItem?->payment?->status,
            'ticket_available' => $registration->ticket !== null,
        ]);
    }

    public function myRegistrations(Request $request): JsonResponse
    {
        $registrations = EventRegistration::where('user_id', $request->user()->id)
            ->with(['event', 'ticket', 'paymentItem.payment'])
            ->when($request->has('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($registrations);
    }

    public function show(EventRegistration $registration): JsonResponse
    {
        // Authorization check
        if ($registration->user_id !== auth()->id() &&
            $registration->registered_by !== auth()->id() &&
            !auth()->user()->hasRole('admin')) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $registration->load([
            'event',
            'user',
            'registeredBy',
            'organization',
            'ticket',
            'paymentItem.payment',
            'groupRegistration'
        ]);

        return response()->json([
            'registration' => $registration,
        ]);
    }

    public function cancel(Request $request, EventRegistration $registration): JsonResponse
    {
        // Authorization check
        if ($registration->user_id !== auth()->id() &&
            $registration->registered_by !== auth()->id() &&
            !auth()->user()->hasRole('admin')) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($registration->status === 'cancelled') {
            return response()->json([
                'message' => 'Registration is already cancelled',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $this->registrationService->cancelRegistration(
            $registration,
            $validated['reason'] ?? null
        );

        return response()->json([
            'message' => 'Registration cancelled successfully',
        ]);
    }
}
