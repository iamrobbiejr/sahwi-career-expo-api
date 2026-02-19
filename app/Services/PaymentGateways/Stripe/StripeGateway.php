<?php

namespace App\Services\PaymentGateways\Stripe;

use App\Models\Payment;
use App\Models\PaymentGateway as PaymentGatewayModel;
use App\Services\PaymentGateways\Contracts\PaymentGatewayInterface;
use Exception;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Stripe\Webhook;


class StripeGateway implements PaymentGatewayInterface
{
    protected PaymentGatewayModel $gateway;
    protected StripeClient $stripe;
    protected ?string $webhookSecret;

    public function __construct()
    {
        $this->gateway = PaymentGatewayModel::where('slug', 'stripe')->firstOrFail();

        $credentials = $this->gateway->credentials ?? [];

        // Prefer DB credentials; fall back to env / config
        $secret = $credentials['secret'] ?? config('services.stripe.secret');

        if (!$secret) {
            throw new Exception('Stripe secret key is not configured.');
        }

        $this->stripe = new StripeClient($secret);
        $this->webhookSecret = $credentials['webhook_secret']
            ?? config('services.stripe.webhook_secret');
    }

    // =========================================================================
    // PaymentGatewayInterface
    // =========================================================================

    /**
     * Create a Stripe Checkout Session and return the redirect URL.
     *
     * @throws ApiErrorException
     * @throws Exception
     */
    public function initializePayment(Payment $payment, array $options = []): array
    {
        $event = $payment->event;
        $user = $payment->user;

        // amount_cents is the DB column; amount is the computed accessor (cents / 100)
        $unitAmountCents = $payment->amount_cents;

        if ($unitAmountCents <= 0) {
            throw new Exception('Payment amount must be greater than zero.');
        }

        $successUrl = ($options['return_url'] ?? url('/')) . '?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $options['cancel_url'] ?? $options['return_url'] ?? url('/');

        try {
            $session = $this->stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($payment->currency ?? 'usd'),
                        'product_data' => [
                            'name' => $event->name ?? 'Event Registration',
                            'description' => 'Event Registration',
                        ],
                        'unit_amount' => $unitAmountCents,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => $payment->payment_reference,
                'customer_email' => $user->email ?? null,
                'metadata' => [
                    'payment_id' => $payment->id,
                    'event_id' => $payment->event_id,
                ],
            ]);

            Log::channel('payments')->info('Stripe: Checkout session created', [
                'payment_id' => $payment->id,
                'session_id' => $session->id,
            ]);

            return [
                'payment_method' => 'redirect',
                'session_id' => $session->id,
                'redirect_url' => $session->url,
                'checkout_url' => $session->url,
                'payment_intent_id' => $session->payment_intent,
            ];

        } catch (Exception $e) {
            Log::channel('payments')->error('Stripe: Initialization failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Verify a payment by querying the Checkout Session.
     *
     * @throws Exception
     */
    public function verifyPayment(Payment $payment): array
    {
        $gatewayResponse = $payment->gateway_response;

        if (!isset($gatewayResponse['initialization']['session_id'])) {
            throw new Exception('Stripe session ID not found in gateway_response.');
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

            Log::channel('payments')->info('Stripe: Payment verified', [
                'payment_id' => $payment->id,
                'stripe_status' => $session->payment_status,
                'internal_status' => $status,
            ]);

            return [
                'status' => $status,
                'transaction_id' => $session->payment_intent,
                'amount' => $session->amount_total,   // in cents, as returned by Stripe
                'raw_response' => $session->toArray(),
            ];

        } catch (Exception $e) {
            Log::channel('payments')->error('Stripe: Verification failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Issue a refund via the Stripe Refunds API.
     *
     * @throws Exception
     */
    public function refundPayment(Payment $payment, int $amountCents): array
    {
        if (!$payment->gateway_transaction_id) {
            throw new Exception('No Stripe PaymentIntent ID on record to refund.');
        }

        try {
            $refund = $this->stripe->refunds->create([
                'payment_intent' => $payment->gateway_transaction_id,
                'amount' => $amountCents,
                'reason' => 'requested_by_customer',
                'metadata' => [
                    'payment_id' => $payment->id,
                ],
            ]);

            Log::channel('payments')->info('Stripe: Refund issued', [
                'payment_id' => $payment->id,
                'refund_id' => $refund->id,
                'amount_cents' => $amountCents,
            ]);

            return [
                'refund_id' => $refund->id,
                'status' => $refund->status,
                'amount' => $refund->amount,   // cents
            ];

        } catch (Exception $e) {
            Log::channel('payments')->error('Stripe: Refund failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle Stripe webhook events.
     *
     * Verifies the `Stripe-Signature` header against the configured
     * webhook secret before processing.
     *
     * @throws Exception
     */
    public function handleWebhook(array $payload): array
    {
        $signature = request()->header('Stripe-Signature');

        if (!$this->webhookSecret) {
            Log::channel('payments')->warning('Stripe: Webhook secret not configured — skipping signature check.');
        } else {
            try {
                Webhook::constructEvent(
                    request()->getContent(),
                    $signature,
                    $this->webhookSecret
                );
            } catch (Exception $e) {
                Log::channel('payments')->error('Stripe: Invalid webhook signature', [
                    'error' => $e->getMessage(),
                ]);
                throw new Exception('Invalid Stripe webhook signature.');
            }
        }

        $type = $payload['type'] ?? '';

        switch ($type) {
            case 'checkout.session.completed':
                $session = $payload['data']['object'] ?? [];
                $ref = $session['client_reference_id'] ?? null;
                $payment = $ref ? Payment::where('payment_reference', $ref)->first() : null;

                Log::channel('payments')->info('Stripe: checkout.session.completed', [
                    'reference' => $ref,
                    'payment_id' => $payment?->id,
                ]);

                return [
                    'payment_id' => $payment?->id,
                    'status' => 'completed',
                    'transaction_id' => $session['payment_intent'] ?? null,
                ];

            case 'payment_intent.payment_failed':
                $intent = $payload['data']['object'] ?? [];
                $payment = Payment::where('gateway_transaction_id', $intent['id'] ?? '')->first();

                Log::channel('payments')->warning('Stripe: payment_intent.payment_failed', [
                    'intent_id' => $intent['id'] ?? null,
                    'payment_id' => $payment?->id,
                ]);

                if ($payment) {
                    $payment->update([
                        'status' => 'failed',
                        'failure_reason' => $intent['last_payment_error']['message'] ?? 'Card declined',
                        'failed_at' => now(),
                    ]);
                }

                return [
                    'payment_id' => $payment?->id,
                    'status' => 'failed',
                ];

            default:
                Log::channel('payments')->info("Stripe: Unhandled webhook event type [{$type}]");
                return ['status' => 'unhandled', 'type' => $type];
        }
    }
}
