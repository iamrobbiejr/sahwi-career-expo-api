<?php

namespace App\Services\PaymentGateways\Stripe;

use App\Models\Payment;
use App\Models\PaymentGateway as PaymentGatewayModel;
use App\Services\PaymentGateways\Contracts\PaymentGatewayInterface;
use Exception;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

class StripeGateway implements PaymentGatewayInterface
{
    protected PaymentGatewayModel $gateway;
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->gateway = PaymentGatewayModel::where('slug', 'stripe')->firstOrFail();
        $credentials = $this->gateway->credentials;

        $this->stripe = new StripeClient($credentials['secret_key']);
    }

    public function initializePayment(Payment $payment, array $options = []): array
    {
        try {
            // Create Stripe Checkout Session
            $session = $this->stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($payment->currency),
                        'product_data' => [
                            'name' => $payment->event->name,
                            'description' => "Event Registration",
                        ],
                        'unit_amount' => $payment->amount_cents,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $options['return_url'] . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $options['cancel_url'] ?? $options['return_url'],
                'client_reference_id' => $payment->payment_reference,
                'customer_email' => $payment->user->email,
                'metadata' => [
                    'payment_id' => $payment->id,
                    'event_id' => $payment->event_id,
                ],
            ]);

            return [
                'session_id' => $session->id,
                'checkout_url' => $session->url,
                'payment_intent_id' => $session->payment_intent,
            ];

        } catch (Exception $e) {
            Log::error('Stripe initialization failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function verifyPayment(Payment $payment): array
    {
        $gatewayResponse = $payment->gateway_response;

        if (!isset($gatewayResponse['initialization']['session_id'])) {
            throw new Exception('Session ID not found');
        }

        try {
            $session = $this->stripe->checkout->sessions->retrieve(
                $gatewayResponse['initialization']['session_id']
            );

            $status = match ($session->payment_status) {
                'paid' => 'completed',
                'unpaid' => 'pending',
                default => 'processing',
            };

            return [
                'status' => $status,
                'transaction_id' => $session->payment_intent,
                'amount' => $session->amount_total / 100,
                'raw_response' => $session->toArray(),
            ];

        } catch (Exception $e) {
            Log::error('Stripe verification failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function refundPayment(Payment $payment, int $amountCents): array
    {
        try {
            $refund = $this->stripe->refunds->create([
                'payment_intent' => $payment->gateway_transaction_id,
                'amount' => $amountCents,
                'reason' => 'requested_by_customer',
                'metadata' => [
                    'payment_id' => $payment->id,
                ],
            ]);

            return [
                'refund_id' => $refund->id,
                'status' => $refund->status,
                'amount' => $refund->amount / 100,
            ];

        } catch (Exception $e) {
            Log::error('Stripe refund failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function handleWebhook(array $payload): array
    {
        $event = $payload;

        // Verify webhook signature
        $signature = request()->header('Stripe-Signature');
        $webhookSecret = $this->gateway->webhook_secret;

        try {
            $event = Webhook::constructEvent(
                request()->getContent(),
                $signature,
                $webhookSecret
            );
        } catch (Exception $e) {
            throw new Exception('Invalid webhook signature');
        }

        // Handle different event types
        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                $payment = Payment::where('payment_reference', $session->client_reference_id)->first();

                return [
                    'payment_id' => $payment?->id,
                    'status' => 'completed',
                    'transaction_id' => $session->payment_intent,
                ];

            case 'payment_intent.payment_failed':
                $intent = $event->data->object;
                // Handle failed payment
                break;
        }

        return ['status' => 'unhandled'];
    }
}
