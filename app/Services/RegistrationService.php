<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\GroupRegistration;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegistrationService
{
    public function registerIndividual(Event $event, User $user, array $data): EventRegistration
    {
        // 1. Handle all Database & File operations inside the transaction
        $registrationData = DB::transaction(function () use ($event, $user, $data) {
            $registration = EventRegistration::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'registration_type' => 'individual',
                'attendee_type' => $user->role,
                'status' => $event->is_paid ? 'pending' : 'confirmed',
                'attendee_name' => $data['attendee_name'] ?? $user->name,
                'attendee_email' => $data['attendee_email'] ?? $user->email,
                'attendee_phone' => $data['attendee_phone'] ?? $user->whatsapp_number,
                'attendee_title' => $data['attendee_title'] ?? $user->title,
                'attendee_organization_id' => $data['attendee_organization_id'] ?? $user->organisation_id,
            ]);

            $event->increment('registrations');

            $ticket = null;
            if (!$event->is_paid) {
                $payment = Payment::create([
                    'event_id' => $event->id,
                    'user_id' => $user->id,
                    'gateway_name' => 'Free',
                    'amount_cents' => 0,
                    'currency' => $event->currency,
                    'status' => 'completed',
                    'paid_at' => now(),
                ]);

                PaymentItem::create([
                    'payment_id' => $payment->id,
                    'event_registration_id' => $registration->id,
                    'description' => "Registration for {$event->name}",
                    'amount_cents' => 0,
                    'quantity' => 1,
                ]);

                // Create ticket & files, but DON'T email yet
                $ticket = app(TicketService::class)->generateTicketRecord($registration, $payment);
            }

            app(RewardService::class)->awardFor($user, 'event_registration', [
                'event_id' => $event->id,
                'registration_id' => $registration->id,
            ]);

            return ['registration' => $registration, 'ticket' => $ticket];
        });

        // 2. Handle the Email outside the transaction
        if ($registrationData['ticket']) {
            app(TicketService::class)->emailTicket($registrationData['ticket']);
        }

        return $registrationData['registration'];
    }

    public function registerGroup(Event $event, User $companyRep, array $members, array $groupData = []): GroupRegistration
    {
        return DB::transaction(function () use ($event, $companyRep, $members, $groupData) {
            // Create group registration
            $groupRegistration = GroupRegistration::create([
                'event_id' => $event->id,
                'registered_by' => $companyRep->id,
                'organization_id' => $companyRep->organisation_id,
                'group_name' => $groupData['group_name'] ?? null,
                'total_members' => count($members),
            ]);

            // For free events, prepare a zero-amount completed payment for the whole group
            $payment = null;
            if (!$event->is_paid) {
                $payment = Payment::create([
                    'event_id' => $event->id,
                    'user_id' => $companyRep->id,
                    'gateway_name' => 'Free',
                    'amount_cents' => 0,
                    'currency' => $event->currency,
                    'status' => 'completed',
                    'paid_at' => now(),
                    'notes' => 'Auto-generated for free group registration',
                ]);
            }

            // Create individual registrations for each member
            foreach ($members as $memberData) {
                $registration = EventRegistration::create([
                    'event_id' => $event->id,
                    'user_id' => $companyRep->id, // The person who registered
                    'registered_by' => $companyRep->id,
                    'group_registration_id' => $groupRegistration->id,
                    'registration_type' => 'group',
                    'attendee_type' => 'company_rep',
                    'status' => $event->is_paid ? 'pending' : 'confirmed',
                    'attendee_name' => $memberData['name'],
                    'attendee_email' => $memberData['email'],
                    'attendee_phone' => $memberData['phone'] ?? null,
                    'attendee_title' => $memberData['title'] ?? null,
                    'attendee_organization_id' => $companyRep->organisation_id,
                    'special_requirements' => $memberData['special_requirements'] ?? null,
                ]);

                // For free events, attach a zero-amount item per member and generate ticket
                if ($payment) {
                    PaymentItem::create([
                        'payment_id' => $payment->id,
                        'event_registration_id' => $registration->id,
                        'description' => "Group registration for {$event->name} - {$registration->attendee_name}",
                        'amount_cents' => 0,
                        'quantity' => 1,
                    ]);

                    app(TicketService::class)->generateTicketRecord($registration, $payment);
                }
            }

            // Update event registrations count
            $event->increment('registrations', count($members));

            // Reward: configured points for organizing a group registration (same key)
            app(RewardService::class)->awardFor($companyRep, 'event_registration', [
                'event_id' => $event->id,
                'group_registration_id' => $groupRegistration->id,
            ]);

            return $groupRegistration;
        });
    }

    public function cancelRegistration(EventRegistration $registration, string $reason = null): bool
    {
        return DB::transaction(function () use ($registration, $reason) {
            $registration->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            // Decrement event registrations
            $registration->event->decrement('registrations');

            // Cancel ticket if exists
            if ($registration->ticket) {
                $registration->ticket->update(['status' => 'cancelled']);
            }

            return true;
        });
    }
}
