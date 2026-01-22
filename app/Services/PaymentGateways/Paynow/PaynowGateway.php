<?php

namespace App\Services\PaymentGateways\Paynow;

use App\Models\Payment;
use App\Models\PaymentGateway as PaymentGatewayModel;
use App\Services\PaymentGateways\Contracts\PaymentGatewayInterface;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaynowGateway implements PaymentGatewayInterface
{
    protected PaymentGatewayModel $gateway;

    public function __construct()
    {
        $this->gateway = PaymentGatewayModel::where('slug', 'paynow')->firstOrFail();
    }

    public function initializePayment(Payment $payment, array $options = []): array
    {
        $credentials = $this->gateway->credentials;

        $data = [
            'id' => $credentials['integration_id'],
            'reference' => $payment->payment_reference,
            'amount' => $payment->amount,
            'additionalinfo' => "Payment for {$payment->event->name}",
            'returnurl' => $options['return_url'],
            'resulturl' => route('webhooks.paynow'),
            'status' => 'Message',
        ];

        if ($options['payment_method'] === 'mobile_money' && isset($options['phone'])) {
            $data['authemail'] = $payment->user->email;
            $data['phone'] = $this->formatPhoneNumber($options['phone']);
        }

        // Generate hash
        $values = array_values($data);
        $values[] = $credentials['integration_key'];
        $hash = hash('sha512', implode('', $values));

        $data['hash'] = strtoupper($hash);

        try {
            $response = Http::asForm()->post('https://www.paynow.co.zw/interface/initiatetransaction', $data);

            $result = $this->parsePaynowResponse($response->body());

            if ($result['status'] !== 'Ok') {
                throw new Exception($result['error'] ?? 'Payment initialization failed');
            }

            return [
                'poll_url' => $result['pollurl'],
                'redirect_url' => $result['browserurl'] ?? null,
                'paynow_reference' => $result['paynowreference'],
                'instructions' => $result['instructions'] ?? null,
            ];

        } catch (Exception $e) {
            Log::error('Paynow initialization failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @throws ConnectionException
     */
    public function verifyPayment(Payment $payment): array
    {
        $gatewayResponse = $payment->gateway_response;

        if (!isset($gatewayResponse['initialization']['poll_url'])) {
            throw new Exception('Poll URL not found in payment data');
        }

        try {
            $response = Http::get($gatewayResponse['initialization']['poll_url']);
            $result = $this->parsePaynowResponse($response->body());

            $status = match ($result['status']) {
                'Paid' => 'completed',
                'Awaiting Delivery', 'Delivered' => 'completed',
                'Cancelled' => 'cancelled',
                'Failed' => 'failed',
                default => 'processing',
            };

            return [
                'status' => $status,
                'transaction_id' => $result['paynowreference'] ?? null,
                'amount' => isset($result['amount']) ? (float) $result['amount'] : null,
                'raw_response' => $result,
            ];

        } catch (Exception $e) {
            Log::error('Paynow verification failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function refundPayment(Payment $payment, int $amountCents): array
    {
        // Paynow doesn't support automatic refunds via API
        // This would need to be done manually through their dashboard

        return [
            'refund_id' => null,
            'status' => 'manual_processing_required',
            'message' => 'Paynow refunds must be processed manually through the dashboard',
        ];
    }

    /**
     * @throws Exception
     */
    public function handleWebhook(array $payload): array
    {
        // Verify webhook authenticity
        $credentials = $this->gateway->credentials;

        // Remove hash from the payload for verification
        $hash = $payload['hash'] ?? '';
        unset($payload['hash']);

        // Generate expected hash
        $values = array_values($payload);
        $values[] = $credentials['integration_key'];
        $expectedHash = strtoupper(hash('sha512', implode('', $values)));

        if ($hash !== $expectedHash) {
            throw new Exception('Invalid webhook signature');
        }

        // Find payment by reference
        $payment = Payment::where('payment_reference', $payload['reference'])->first();

        if (!$payment) {
            throw new Exception('Payment not found');
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
            'amount' => isset($payload['amount']) ? (float) $payload['amount'] : null,
        ];
    }

    protected function parsePaynowResponse(string $response): array
    {
        $lines = explode("\n", $response);
        $result = [];

        foreach ($lines as $line) {
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $result[trim(strtolower($parts[0]))] = trim($parts[1]);
            }
        }

        return $result;
    }

    protected function formatPhoneNumber(string $phone): string
    {
        // Remove any non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Ensure it starts with the country code (263 for Zimbabwe)
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
