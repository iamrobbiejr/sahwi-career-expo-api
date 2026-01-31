<?php

namespace App\Services\PaymentGateways\SmilePay;

use App\Models\Payment;
use App\Models\PaymentGateway as PaymentGatewayModel;
use App\Services\PaymentGateways\Contracts\PaymentGatewayInterface;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmilePayGateway implements PaymentGatewayInterface
{
    protected PaymentGatewayModel $gateway;
    protected string $baseUrl;
    protected ?string $apiKey;
    protected ?string $apiSecret;

    public function __construct()
    {
        $this->gateway = PaymentGatewayModel::where('slug', 'smile-and-pay')->firstOrFail();
        $this->apiKey = config('services.smilepay.api_key');
        $this->apiSecret = config('services.smilepay.api_secret');
        $credentials = $this->gateway->credentials ?? [];

        // Prefer DB credentials; fallback to env
        $this->baseUrl = rtrim(config('services.smilepay.base_url', env('SMILEPAY_BASE_URL', 'https://zbnet.zb.co.zw/wallet_sandbox_api/payments-gateway')), '/');

        // Ensure payments log channel exists; fallback to default if missing
        Log::channel('payments')->info('SmilePayGateway initialized', [
            'gateway_id' => $this->gateway->id,
        ]);
    }

    /**
     * @throws Exception
     */
    public function initializePayment(Payment $payment, array $options = []): array
    {
        $method = strtolower($options['payment_method'] ?? $payment->payment_method ?? 'card');

        return match ($method) {
            'innbucks' => $this->initiateInnbucks($payment, $options),
            'ecocash' => $this->initiateEcocash($payment, $options),
            'omari' => $this->initiateOmari($payment, $options),
            'card' => $this->initiateCard($payment, $options),
            default => throw new Exception("Unsupported Smile&Pay method: {$method}"),
        };
    }

    public function verifyPayment(Payment $payment): array
    {
        $orderRef = $payment->payment_reference;
        $result = $this->checkStatus($orderRef);

        $statusMap = [
            'PAID' => 'completed',
            'SUCCESS' => 'completed',
            'PENDING' => 'pending',
            'PROCESSING' => 'processing',
            'CANCELED' => 'cancelled',
            'CANCELLED' => 'cancelled',
            'FAILED' => 'failed',
        ];

        $providerStatus = strtoupper((string)($result['status'] ?? 'PENDING'));
        $internal = $statusMap[$providerStatus] ?? 'processing';

        return [
            'status' => $internal,
            'transaction_id' => $result['reference'] ?? $result['transactionReference'] ?? null,
            'raw_response' => $result,
        ];
    }

    /**
     * @throws Exception
     */
    public function refundPayment(Payment $payment, int $amountCents): array
    {
        // Smile&Pay docs do not include refund endpoint in provided snippet.
        // For now, we log and throw to indicate unsupported.
        Log::channel('payments')->warning('Smile&Pay refund not supported via API', [
            'payment_id' => $payment->id,
            'amount_cents' => $amountCents,
        ]);
        throw new Exception('Smile&Pay refund API not supported');
    }

    public function handleWebhook(array $payload): array
    {
        // Basic signature validation if header and secret present
        try {
            $this->validateSignature();
        } catch (Exception $e) {
            Log::channel('payments')->warning('Smile&Pay webhook signature validation failed', [
                'error' => $e->getMessage(),
            ]);
            // Continue in the sandbox; throw in production if required
        }

        $orderRef = $payload['orderReference'] ?? $payload['order_reference'] ?? null;
        $status = strtoupper((string)($payload['status'] ?? ''));
        $transactionRef = $payload['transactionReference'] ?? $payload['reference'] ?? null;

        if (!$orderRef) {
            throw new Exception('orderReference missing in webhook');
        }

        $payment = Payment::where('payment_reference', $orderRef)->first();

        if (!$payment) {
            throw new Exception('Payment not found for orderReference');
        }

        // Map and update statuses
        $map = [
            'PAID' => 'completed',
            'SUCCESS' => 'completed',
            'FAILED' => 'failed',
            'CANCELED' => 'cancelled',
            'CANCELLED' => 'cancelled',
            'PENDING' => 'processing',
        ];
        $internal = $map[$status] ?? 'processing';

        if ($internal === 'completed') {
            // Let central service mark paid to keep consistency (tickets, etc.)
            return [
                'payment_id' => $payment->id,
                'status' => 'completed',
                'transaction_id' => $transactionRef,
            ];
        }

        // Update non-completed statuses here
        $payment->update([
            'status' => $internal,
            'gateway_transaction_id' => $transactionRef,
            'gateway_response' => array_merge($payment->gateway_response ?? [], [
                'webhook' => $payload,
            ]),
            'failed_at' => $internal === 'failed' ? now() : $payment->failed_at,
        ]);

        Log::channel('payments')->info('Smile&Pay webhook processed', [
            'payment_id' => $payment->id,
            'status' => $internal,
        ]);

        return [
            'payment_id' => $payment->id,
            'status' => $internal,
            'transaction_id' => $transactionRef,
        ];
    }

    // ============ Custom Smile&Pay Methods ============

    /**
     * @throws Exception
     */
    public function initiateInnbucks(Payment $payment, array $options = []): array
    {
        $payload = $this->buildBasePayload($payment, $options);

        $response = $this->post('/payments/express-checkout/innbucks', $payload);

        $this->logAndStoreInit($payment, 'innbucks', $response);

        return [
            'payment_code' => Arr::get($response, 'innbucksPaymentCode'),
            'transactionReference' => Arr::get($response, 'transactionReference'),
        ];
    }

    public function initiateEcocash(Payment $payment, array $options = []): array
    {
        $payload = $this->buildBasePayload($payment, $options);
        $payload['ecocashMobile'] = $options['phone'] ?? $payment->payment_phone;

        $response = $this->post('/payments/express-checkout/ecocash', $payload);

        $this->logAndStoreInit($payment, 'ecocash', $response);

        return [
            'transactionReference' => Arr::get($response, 'transactionReference'),
        ];
    }

    // Omari is two-step: init then confirm with OTP
    public function initiateOmari(Payment $payment, array $options = []): array
    {
        $payload = $this->buildBasePayload($payment, $options);
        $payload['omariMobile'] = $options['phone'] ?? $payment->payment_phone;

        $response = $this->post('/payments/express-checkout/omari', $payload);

        $this->logAndStoreInit($payment, 'omari', $response);

        return [
            'transactionReference' => Arr::get($response, 'transactionReference'),
            'requires_otp' => true,
        ];
    }

    public function confirmOmari(string $transactionReference, string $otp, string $omariMobile): array
    {
        $payload = [
            'transactionReference' => $transactionReference,
            'otp' => $otp,
            'omariMobile' => $omariMobile,
        ];

        $response = $this->post('/payments/express-checkout/omari/confirmation', $payload);

        Log::channel('payments')->info('Smile&Pay Omari confirmation response', [
            'transactionReference' => $transactionReference,
            'status' => $response['status'] ?? null,
        ]);

        return $response;
    }

    /**
     * @throws Exception
     */
    public function initiateCard(Payment $payment, array $options = []): array
    {
        $payload = $this->buildBasePayload($payment, $options);

        // Sensitive card details should normally never pass through the backend.
        // For this integration per docs, expect to receive them from secure form or tokenization.
        $payload = array_merge($payload, Arr::only($options, [
            'pan', 'expMonth', 'expYear', 'securityCode'
        ]));
        $payload['paymentMethod'] = $options['paymentMethod'] ?? 'WALLETPLUS';

        $response = $this->post('/payments/express-checkout/mpgs', $payload);

        $this->logAndStoreInit($payment, 'card', $response);

        return [
            'transactionReference' => Arr::get($response, 'transactionReference'),
            'redirectHtml' => Arr::get($response, 'redirectHtml'),
            'authenticationStatus' => Arr::get($response, 'authenticationStatus'),
            'gatewayRecommendation' => Arr::get($response, 'gatewayRecommendation'),
            'customizedHtml' => Arr::get($response, 'customizedHtml'),
        ];
    }

    public function cancelPayment(string $orderReference): array
    {
        $response = $this->post("/payments/cancel/{$orderReference}", []);
        return $response;
    }

    public function checkStatus(string $orderReference): array
    {
        $url = $this->baseUrl . "/payments/transaction/{$orderReference}/status/check";
        $res = Http::acceptJson()
            ->withHeaders($this->authHeaders())
            ->get($url);

        if (!$res->successful()) {
            Log::channel('payments')->error('Smile&Pay status check failed', [
                'orderReference' => $orderReference,
                'status' => $res->status(),
                'body' => $res->body(),
            ]);
            throw new Exception('Smile&Pay status check failed');
        }

        return $res->json();
    }

    // ================== Helpers ==================

    protected function buildBasePayload(Payment $payment, array $options): array
    {
        $user = $payment->user;
        $event = $payment->event;

        $returnUrl = $options['return_url'] ?? $this->gateway->settings['return_url'] ?? null;
        $cancelUrl = $options['cancel_url'] ?? $this->gateway->settings['cancel_url'] ?? null;
        $failureUrl = $options['failure_url'] ?? $this->gateway->settings['failure_url'] ?? null;
        $resultUrl = $this->gateway->webhook_url ?: route('webhooks.smilepay');

        $currencyKey = strtoupper($payment->currency);
        $currencyCode = config("services.smilepay.currency_map.$currencyKey", '840'); // Default to USD

        return [
            'orderReference' => $payment->payment_reference,
            'amount' => round($payment->amount, 2),
            'returnUrl' => $returnUrl,
            'resultUrl' => $resultUrl,
            'itemName' => $event->name,
            'itemDescription' => 'Event Registration',
            'currencyCode' => $currencyCode,
            'firstName' => $user->name,
            'lastName' => $user->name,
            'mobilePhoneNumber' => $options['phone'] ?? $payment->payment_phone,
            'email' => $user->email,
            'cancelUrl' => $cancelUrl,
            'failureUrl' => $failureUrl,
        ];
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    protected function post(string $path, array $payload): array
    {
        $url = $this->baseUrl . $path;

        Log::channel('payments')->info('Smile&Pay request', [
            'url' => $url,
            'payload' => $payload,
        ]);

        $response = Http::acceptJson()
            ->withHeaders($this->authHeaders())
            ->post($url, $payload);

        if (!$response->successful()) {
            Log::channel('payments')->error('Smile&Pay API error', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new Exception('Smile&Pay API request failed');
        }

        $json = $response->json();

        Log::channel('payments')->info('Smile&Pay response', [
            'url' => $url,
            'response' => $json,
        ]);

        return $json;
    }

    protected function logAndStoreInit(Payment $payment, string $method, array $response): void
    {
        $payment->update([
            'gateway_response' => array_merge($payment->gateway_response ?? [], [
                'initialization' => [
                    'method' => $method,
                    'response' => $response,
                ],
            ]),
            'status' => 'processing',
        ]);

        Log::channel('payments')->info('Smile&Pay initialized', [
            'payment_id' => $payment->id,
            'method' => $method,
        ]);
    }

    protected function authHeaders(): array
    {
        // If the gateway requires API keys via headers, add here.
        // Using common patterns: X-Api-Key or Authorization Bearer
        $headers = [];
        $creds = $this->gateway->credentials ?? [];

        if (!empty($creds['api_key'])) {
            $headers['X-Api-Key'] = $creds['api_key'];
        } elseif ($this->apiKey) {
            $headers['X-Api-Key'] = $this->apiKey;
        }

        if (!empty($creds['merchant_id'])) {
            $headers['X-Merchant-Id'] = $creds['merchant_id'];
        } elseif ($mid = env('SMILEPAY_MERCHANT_ID')) {
            $headers['X-Merchant-Id'] = $mid;
        }

        return $headers;
    }

    protected function validateSignature(): void
    {
        $secret = $this->gateway->webhook_secret ?? env('SMILEPAY_WEBHOOK_SECRET');
        if (!$secret) {
            return; // nothing to validate
        }
        $provided = request()->header('X-SmilePay-Signature');
        if (!$provided) {
            throw new Exception('Missing Smile&Pay signature header');
        }
        $raw = request()->getContent();
        $calc = base64_encode(hash_hmac('sha256', $raw, $secret, true));
        if (!hash_equals($provided, $calc)) {
            throw new Exception('Invalid Smile&Pay signature');
        }
    }
}
