<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\GroupRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegistrationService
{
    public function registerIndividual(Event $event, User $user, array $data): EventRegistration
    {
        return DB::transaction(function () use ($event, $user, $data) {
            // Create registration
            $registration = EventRegistration::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'registration_type' => 'individual',
                'attendee_type' => $user->role,
                'status' => 'pending',
                'attendee_name' => $data['attendee_name'] ?? $user->name,
                'attendee_email' => $data['attendee_email'] ?? $user->email,
                'attendee_phone' => $data['attendee_phone'] ?? $user->whatsapp_number,
                'attendee_title' => $data['attendee_title'] ?? $user->title,
                'attendee_organization_id' => $data['attendee_organization_id'] ?? $user->organisation_id,
                'special_requirements' => $data['special_requirements'] ?? null,
                'custom_fields' => $data['custom_fields'] ?? null,
            ]);

            // Increment event registrations count
            $event->increment('registrations');

            return $registration;
        });
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

            // Create individual registrations for each member
            foreach ($members as $memberData) {
                EventRegistration::create([
                    'event_id' => $event->id,
                    'user_id' => $companyRep->id, // The person who registered
                    'registered_by' => $companyRep->id,
                    'group_registration_id' => $groupRegistration->id,
                    'registration_type' => 'group',
                    'attendee_type' => 'company_rep',
                    'status' => 'pending',
                    'attendee_name' => $memberData['name'],
                    'attendee_email' => $memberData['email'],
                    'attendee_phone' => $memberData['phone'] ?? null,
                    'attendee_title' => $memberData['title'] ?? null,
                    'attendee_organization_id' => $companyRep->organisation_id,
                    'special_requirements' => $memberData['special_requirements'] ?? null,
                ]);
            }

            // Update event registrations count
            $event->increment('registrations', count($members));

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
