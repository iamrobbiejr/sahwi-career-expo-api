<?php

namespace App\Services\PaymentGateways\Paynow;

use App\Models\Payment;
use App\Models\PaymentGateway as PaymentGatewayModel;
use App\Services\PaymentGateways\Contracts\PaymentGatewayInterface;
use Exception;
use Illuminate\Support\Facades\Log;
use Paynow\Payments\Paynow;

class PaynowGateway implements PaymentGatewayInterface
{
    protected PaymentGatewayModel $gatewayModel;
    protected Paynow $paynow;

    public function __construct()
    {
        $this->gatewayModel = PaymentGatewayModel::where('slug', 'paynow')->firstOrFail();

        $credentials = $this->gatewayModel->credentials;

        // Use foreign integration keys (for USD / mobile money) by default.
        // The initializePayment method can override these per-call if needed.
        $integrationId = $credentials['foreign_integration_id'] ?? $credentials['integration_id'];
        $integrationKey = $credentials['foreign_integration_key'] ?? $credentials['integration_key'];

        $this->paynow = new Paynow(
            $integrationId,
            $integrationKey,
            route('payments.callback'),   // default return URL (overridden per payment)
            route('webhooks.paynow')      // result URL for Paynow server-to-server notification
        );
    }

    // -----------------------------------------------------------------------
    // PaymentGatewayInterface implementation
    // -----------------------------------------------------------------------

    /**
     * Initialize a payment.
     *
     * Accepted $options keys:
     *   - return_url  (string)  : where Paynow redirects the user after payment
     *   - cancel_url  (string)  : not used by Paynow but kept for interface parity
     *   - payment_method (string): 'mobile_money' | 'redirect' (default: 'redirect')
     *   - phone   (string)  : required when payment_method = 'mobile_money'
     *   - network (string)  : 'ecocash' | 'onemoney' | 'telecash' (default: 'ecocash')
     */
    public function initializePayment(Payment $payment, array $options = []): array
    {
        $method = $options['payment_method'] ?? 'redirect';

        if ($method === 'mobile_money') {
            return $this->initializeMobilePayment($payment, $options);
        }

        return $this->initializeRedirectPayment($payment, $options);
    }

    /**
     * Verify payment status by polling Paynow.
     */
    public function verifyPayment(Payment $payment): array
    {
        $gatewayResponse = $payment->gateway_response;

        if (!isset($gatewayResponse['initialization']['poll_url'])) {
            throw new Exception('Poll URL not found in payment data');
        }

        try {
            $status = $this->paynow->pollTransaction($gatewayResponse['initialization']['poll_url']);

            $mappedStatus = match ($status->status()) {
                'Paid', 'Awaiting Delivery', 'Delivered' => 'completed',
                'Cancelled' => 'cancelled',
                'Failed' => 'failed',
                default => 'processing',
            };

            Log::channel('daily')->debug('Paynow verify – status: ' . $status->status());

            return [
                'status' => $mappedStatus,
                'transaction_id' => null,   // Paynow SDK does not expose paynowreference directly
                'amount' => $status->amount(),
                'raw_status' => $status->status(),
            ];

        } catch (Exception $e) {
            Log::error('Paynow verification failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Refunds are not supported automatically by Paynow.
     */
    public function refundPayment(Payment $payment, int $amountCents): array
    {
        return [
            'refund_id' => null,
            'status' => 'manual_processing_required',
            'message' => 'Paynow refunds must be processed manually through the Paynow dashboard',
        ];
    }

    /**
     * Handle Paynow server-to-server result notification (webhook).
     */
    public function handleWebhook(array $payload): array
    {
        $credentials = $this->gatewayModel->credentials;
        $integrationKey = $credentials['foreign_integration_key'] ?? $credentials['integration_key'];

        // Verify hash
        $hash = $payload['hash'] ?? '';
        unset($payload['hash']);

        $values = array_values($payload);
        $values[] = $integrationKey;
        $expectedHash = strtoupper(hash('sha512', implode('', $values)));

        if (strtoupper($hash) !== $expectedHash) {
            throw new Exception('Invalid Paynow webhook signature');
        }

        $payment = Payment::where('payment_reference', $payload['reference'])->first();

        if (!$payment) {
            throw new Exception('Payment not found for reference: ' . ($payload['reference'] ?? 'N/A'));
        }

        $status = match ($payload['status']) {
            'Paid', 'Awaiting Delivery', 'Delivered' => 'completed',
            'Cancelled' => 'cancelled',
            'Failed' => 'failed',
            default => 'processing',
        };

        return [
            'payment_id' => $payment->id,
            'status' => $status,
            'transaction_id' => $payload['paynowreference'] ?? null,
            'amount' => isset($payload['amount']) ? (float)$payload['amount'] : null,
        ];
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Paynow Standard – redirects user to Paynow browser page.
     */
    private function initializeRedirectPayment(Payment $payment, array $options): array
    {
        $returnUrl = $options['return_url'] ?? route('payments.callback');
        $this->paynow->setReturnUrl($returnUrl);
        $this->paynow->setResultUrl(route('webhooks.paynow'));

        Log::channel('daily')->debug('Paynow Standard – initiating redirect payment', [
            'payment_id' => $payment->id,
            'return_url' => $returnUrl,
        ]);

        try {
            $paynowPayment = $this->paynow->createPayment(
                $payment->payment_reference,
                $payment->user->email
            );

            $paynowPayment->add(
                "Event: {$payment->event->name}",
                $payment->amount   // amount accessor converts cents → dollars
            );

            $response = $this->paynow->send($paynowPayment);

            if (!$response->success()) {
                throw new Exception('Paynow standard payment initialization failed');
            }

            Log::channel('daily')->info('Paynow Standard – redirect URL: ' . $response->redirectUrl());

            return [
                'payment_method' => 'redirect',
                'poll_url' => $response->pollUrl(),
                'redirect_url' => $response->redirectUrl(),
                'instructions' => $response->instructions(),
            ];

        } catch (Exception $e) {
            Log::error('Paynow Standard payment error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Paynow Express – initiates mobile money push (EcoCash / OneMoney / Telecash).
     */
    private function initializeMobilePayment(Payment $payment, array $options): array
    {
        if (empty($options['phone'])) {
            throw new Exception('Phone number is required for mobile money payments');
        }

        $phone = $this->formatPhoneNumber($options['phone']);
        $network = strtolower($options['network'] ?? 'ecocash');

        $returnUrl = $options['return_url'] ?? route('payments.callback');
        $this->paynow->setReturnUrl($returnUrl);
        $this->paynow->setResultUrl(route('webhooks.paynow'));

        Log::channel('daily')->debug('Paynow Express – initiating mobile payment', [
            'payment_id' => $payment->id,
            'phone' => $phone,
            'network' => $network,
        ]);

        try {
            $paynowPayment = $this->paynow->createPayment(
                $payment->payment_reference,
                $payment->user->email
            );

            $paynowPayment->add(
                "Event: {$payment->event->name}",
                $payment->amount
            );

            $response = $this->paynow->sendMobile($paynowPayment, $phone, $network);

            if (!$response->success()) {
                throw new Exception('Paynow Express payment initialization failed');
            }

            $pollUrl = $response->pollUrl();
            $instructions = $response->instructions();

            Log::channel('daily')->info('Paynow Express – poll URL: ' . $pollUrl);

            return [
                'payment_method' => 'mobile_money',
                'poll_url' => $pollUrl,
                'instructions' => $instructions,
                // Convenience deep-link to the Paynow transaction view
                'paynow_transaction_url' => 'https://www.paynow.co.zw/Transaction/TransactionView/?' . explode('?', $pollUrl)[1] ?? '',
            ];

        } catch (Exception $e) {
            Log::error('Paynow Express payment error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Normalise a phone number to the Zimbabwean international format (263XXXXXXXXX).
     */
    protected function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (!str_starts_with($phone, '263')) {
            if (str_starts_with($phone, '0')) {
                $phone = '263' . substr($phone, 1);
            } else {
                $phone = '263' . $phone;
            }
        }

        return $phone;
    }
}
