<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\PaymentItem;
use App\Models\Refund;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

class PaymentService
{
    /**
     * @throws Throwable
     */
    public function createPayment(Event $event, User $user, array $registrationIds, string $gatewaySlug): Payment
    {
        return DB::transaction(function () use ($event, $user, $registrationIds, $gatewaySlug) {
            $registrations = EventRegistration::whereIn('id', $registrationIds)
                ->where('event_id', $event->id)
                ->get();

            if ($registrations->isEmpty()) {
                throw new Exception('No valid registrations found');
            }

            // Calculate total amount
            $totalAmountCents = $event->price_cents * $registrations->count();

            $gateway = PaymentGateway::where('slug', $gatewaySlug)
                ->where('is_active', true)
                ->firstOrFail();

            // Create payment
            $payment = Payment::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'payment_gateway_id' => $gateway->id,
                'gateway_name' => $gateway->name,
                'amount_cents' => $totalAmountCents,
                'currency' => $event->currency,
                'status' => 'pending',
            ]);

            // Create payment items
            foreach ($registrations as $registration) {
                PaymentItem::create([
                    'payment_id' => $payment->id,
                    'event_registration_id' => $registration->id,
                    'description' => "Registration for {$event->name} - {$registration->attendee_name}",
                    'amount_cents' => $event->price_cents,
                    'quantity' => 1,
                ]);
            }

            return $payment;
        });
    }

    public function markAsPaid(Payment $payment, array $gatewayData): bool
    {
        return DB::transaction(function () use ($payment, $gatewayData) {
            $payment->update([
                'status' => 'completed',
                'gateway_transaction_id' => $gatewayData['transaction_id'] ?? null,
                'gateway_response' => $gatewayData,
                'paid_at' => now(),
            ]);

            // Update all related registrations to confirmed
            foreach ($payment->items as $item) {
                $item->registration->update([
                    'status' => 'confirmed',
                ]);
            }

            // Generate tickets
            app(TicketService::class)->generateTicketsForPayment($payment);

            return true;
        });
    }

    public function processRefund(Payment $payment, int $amountCents, string $reason, User $processedBy): Refund
    {
        return DB::transaction(function () use ($payment, $amountCents, $reason, $processedBy) {
            $refund = Refund::create([
                'payment_id' => $payment->id,
                'processed_by' => $processedBy->id,
                'amount_cents' => $amountCents,
                'currency' => $payment->currency,
                'status' => 'pending',
                'reason' => $reason,
            ]);

            // Update payment status
            if ($amountCents >= $payment->amount_cents) {
                $payment->update(['status' => 'refunded', 'refunded_at' => now()]);
            } else {
                $payment->update(['status' => 'partially_refunded']);
            }

            return $refund;
        });
    }
}
