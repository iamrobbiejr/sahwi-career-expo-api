<?php

namespace App\Services\PaymentGateways\SahwiPay;

use App\Models\Payment;
use App\Models\PaymentGateway as PaymentGatewayModel;
use App\Services\PaymentGateways\Contracts\PaymentGatewayInterface;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * SahwiPay (Sahwi.net Visa/Mastercard) Gateway
 *
 * Payment flow:
 *   1. Build a URL-encoded query string of transaction params.
 *   2. Base64-encode the query string.
 *   3. Redirect the user to: https://pay.sahwi.net/#/visa-mastercard/{base64}
 *   4. After payment, Sahwi redirects the user back to the `returnUrl` with
 *      query params: ?reference={ref}&serverId={sid}&status=SUCCESS|FAILED
 *   5. The frontend reads `status` from the return URL and calls
 *      POST /api/v1/payments/{id}/verify to confirm with the server.
 *
 * NOTE: SahwiPay does NOT push webhooks. Verification is triggered by the
 *       frontend polling after the redirect-back.
 */
class SahwiPayGateway implements PaymentGatewayInterface
{
    /** Entry point for the hosted payment page */
    const BASE_URL = 'https://pay.sahwi.net/#/visa-mastercard/';

    /** Source identifier passed to Sahwi */
    const TXN_SOURCE = 'SEAL_TICKETS_WEB_APP';

    protected PaymentGatewayModel $gateway;

    public function __construct()
    {
        $this->gateway = PaymentGatewayModel::where('slug', 'sahwipay')->firstOrFail();
    }

    // =========================================================================
    // PaymentGatewayInterface
    // =========================================================================

    /**
     * Build the SahwiPay redirect URL and return it to the controller.
     *
     * Expected $options keys:
     *   - return_url  (string)  URL Sahwi will redirect the user back to
     *   - currency    (string)  ISO 4217 code, e.g. "USD" (default: "USD")
     *
     * @throws Exception
     */
    public function initializePayment(Payment $payment, array $options = []): array
    {
        if ($payment->status === 'completed') {
            throw new Exception('Payment already completed.');
        }

        $user = $payment->user;
        $event = $payment->event;
        $returnUrl = $options['return_url'] ?? route('payments.callback');
        $currency = strtoupper($options['currency'] ?? $payment->currency ?? 'USD');

        // SahwiPay expects amount in full units (e.g. 10.00), not cents
        $amount = round($payment->amount, 2);

        // Compose the param string exactly as sahwigate does
        $paramString = http_build_query([
            'description' => $event->name . ' - Event Registration',
            'txnSource' => self::TXN_SOURCE,
            'currencyCode' => $currency,
            'amount' => $amount,
            'reference' => $payment->payment_reference,
            'email' => $user->email ?? '',
            'clientId' => $payment->id,
            'returnUrl' => $returnUrl,
        ]);

        // Sahwi reads the query string as a base64 blob
        $redirectUrl = self::BASE_URL . base64_encode($paramString);

        Log::channel('payments')->info('SahwiPay: Payment URL generated', [
            'payment_id' => $payment->id,
            'redirect_url' => $redirectUrl,
        ]);

        return [
            'payment_method' => 'redirect',
            'redirect_url' => $redirectUrl,
            'poll_url' => null,   // SahwiPay has no poll endpoint
            'instructions' => null,
        ];
    }

    /**
     * Verify a payment after the user returns from SahwiPay.
     *
     * Because SahwiPay has no server-side status API, we trust the
     * `status` query param that Sahwi appended to the return URL.
     * The frontend must pass `sahwi_status` (raw) in the verify request body,
     * or we fall back to checking whether the payment was already marked paid
     * by a previous call.
     *
     * Handled via WebhookController::paynow → but for SahwiPay, use
     * PaymentController::verify which calls this method.
     */
    public function verifyPayment(Payment $payment): array
    {
        // Re-read the status that the frontend forwarded (stored during handleWebhook)
        $raw = $payment->gateway_response['sahwi_return_status'] ?? null;

        if ($raw === null) {
            // Payment hasn't been confirmed via the return redirect yet
            return [
                'status' => 'pending',
                'transaction_id' => null,
                'raw_response' => [],
            ];
        }

        $status = strtoupper($raw) === 'SUCCESS' ? 'completed' : 'failed';

        return [
            'status' => $status,
            'transaction_id' => $payment->payment_reference,
            'raw_response' => ['sahwi_status' => $raw],
        ];
    }

    /**
     * SahwiPay does not expose a public refund API.
     * Refunds must be processed manually through the Sahwi merchant dashboard.
     *
     * @throws Exception always
     */
    public function refundPayment(Payment $payment, int $amountCents): array
    {
        Log::channel('payments')->warning('SahwiPay: Refund not supported via API', [
            'payment_id' => $payment->id,
            'amount_cents' => $amountCents,
        ]);

        throw new Exception('SahwiPay does not support API refunds. Please process via the Sahwi merchant dashboard.');
    }

    /**
     * Handle the "return" callback from SahwiPay.
     *
     * SahwiPay doesn't send traditional POST webhooks; it appends status to
     * the browser return URL. The frontend should call this endpoint with the
     * Sahwi return params so the server can record and verify the outcome.
     *
     * Expected $payload keys (query params forwarded by the frontend):
     *   - reference  (string)  The payment_reference we sent
     *   - status     (string)  SUCCESS | FAILED | CANCELLED
     *   - serverId   (string)  Sahwi internal server ID (for logging)
     *
     * @throws Exception
     */
    public function handleWebhook(array $payload): array
    {
        $reference = $payload['reference'] ?? null;
        $status = strtoupper($payload['status'] ?? '');

        if (!$reference) {
            throw new Exception('SahwiPay return: missing reference');
        }

        $payment = Payment::where('payment_reference', $reference)->first();

        if (!$payment) {
            throw new Exception("SahwiPay return: payment not found for reference [{$reference}]");
        }

        // Store the raw Sahwi status so verifyPayment() can read it
        $payment->update([
            'gateway_response' => array_merge($payment->gateway_response ?? [], [
                'sahwi_return_status' => $status,
                'sahwi_server_id' => $payload['serverId'] ?? null,
                'sahwi_raw' => $payload,
            ]),
        ]);

        Log::channel('payments')->info('SahwiPay: Return received', [
            'payment_id' => $payment->id,
            'status' => $status,
        ]);

        if ($status === 'SUCCESS') {
            return [
                'payment_id' => $payment->id,
                'status' => 'completed',
                'transaction_id' => $reference,
            ];
        }

        // Mark non-success statuses immediately
        $internalStatus = match ($status) {
            'CANCELLED' => 'cancelled',
            default => 'failed',
        };

        $payment->update([
            'status' => $internalStatus,
            'failed_at' => now(),
        ]);

        return [
            'payment_id' => $payment->id,
            'status' => $internalStatus,
        ];
    }
}
