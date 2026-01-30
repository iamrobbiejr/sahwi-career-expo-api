<?php

namespace Tests\Feature\Api\EventRegistrations;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FreeEventRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Bind a lightweight fake TicketService to avoid heavy IO in tests
        $this->app->bind(TicketService::class, function () {
            return new class extends TicketService {
                public function generateTicketRecord(EventRegistration $registration, Payment $payment = null): Ticket
                {
                    // Create a minimal ticket record without generating barcode/PDF/email
                    return Ticket::create([
                        'event_registration_id' => $registration->id,
                        'payment_id' => $payment?->id,
                        'ticket_number' => $registration->ticket_number ?? ('T-' . uniqid()),
                        'status' => 'active',
                    ]);
                }
            };
        });

        // Ensure required roles exist for middleware checks
        if (!Role::where('name', 'company_rep')->where('guard_name', 'web')->exists()) {
            Role::create(['name' => 'company_rep', 'guard_name' => 'web']);
        }
    }

    public function test_individual_free_event_registration_auto_confirms_and_creates_zero_payment_and_ticket(): void
    {
        // Create a user
        $user = User::factory()->create([
            'role' => 'student',
        ]);

        // Create a free active event
        $event = Event::create([
            'name' => 'Free Expo',
            'status' => 'active',
            'created_by' => $user->id,
            'is_paid' => false,
            'price_cents' => 0,
            'currency' => 'USD',
        ]);

        $payload = [
            'attendee_name' => 'John Doe',
            'attendee_email' => 'john@example.com',
        ];

        $response = $this->actingAs($user)
            ->postJson("/api/v1/events/{$event->id}/register", $payload);

        $response->assertCreated()
            ->assertJsonPath('next_step', 'complete')
            ->assertJsonPath('registration.status', 'confirmed');

        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id,
            'attendee_email' => 'john@example.com',
            'status' => 'confirmed',
        ]);

        $registration = EventRegistration::where('event_id', $event->id)->firstOrFail();

        // Payment with amount 0 and completed status exists
        $this->assertDatabaseHas('payments', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'amount_cents' => 0,
            'status' => 'completed',
        ]);

        // Ticket exists for the registration
        $this->assertDatabaseHas('tickets', [
            'event_registration_id' => $registration->id,
            'status' => 'active',
        ]);
    }

    public function test_group_free_event_registration_auto_confirms_and_creates_zero_payment_items_and_tickets(): void
    {
        // Company representative user
        $companyRep = User::factory()->create([
            'role' => 'company_rep',
        ]);
        $companyRep->assignRole('company_rep');

        // Create a free active event
        $event = Event::create([
            'name' => 'Free Job Fair',
            'status' => 'active',
            'created_by' => $companyRep->id,
            'is_paid' => false,
            'price_cents' => 0,
            'currency' => 'USD',
        ]);

        $payload = [
            'group_name' => 'Team A',
            'members' => [
                ['name' => 'Alice', 'email' => 'alice@example.com'],
                ['name' => 'Bob', 'email' => 'bob@example.com'],
            ],
        ];

        $response = $this->actingAs($companyRep)
            ->postJson("/api/v1/events/{$event->id}/register-group", $payload);

        $response->assertCreated()
            ->assertJsonPath('next_step', 'complete')
            ->assertJsonPath('total_members', 2);

        // All member registrations should be confirmed
        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id,
            'attendee_email' => 'alice@example.com',
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id,
            'attendee_email' => 'bob@example.com',
            'status' => 'confirmed',
        ]);

        // Single payment with amount 0 and completed status
        $this->assertDatabaseHas('payments', [
            'event_id' => $event->id,
            'user_id' => $companyRep->id,
            'amount_cents' => 0,
            'status' => 'completed',
        ]);

        $registrations = EventRegistration::where('event_id', $event->id)->get();
        foreach ($registrations as $reg) {
            $this->assertDatabaseHas('tickets', [
                'event_registration_id' => $reg->id,
                'status' => 'active',
            ]);
        }
    }
}
