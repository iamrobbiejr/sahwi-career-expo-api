<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WebhookLog;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function paynow(Request $request)
    {
        return $this->handleWebhook('paynow', $request->all());
    }

    public function paypal(Request $request)
    {
        return $this->handleWebhook('paypal', $request->all());
    }

    public function stripe(Request $request)
    {
        return $this->handleWebhook('stripe', $request->all());
    }

    public function smilepay(Request $request)
    {
        return $this->handleWebhook('smilepay', $request->all());
    }

    protected function handleWebhook(string $gatewaySlug, array $payload)
    {
        // Log webhook
        $webhookLog = WebhookLog::create([
            'payment_gateway_id' => \App\Models\PaymentGateway::where('slug', $gatewaySlug)->first()?->id,
            'event_type' => $payload['event_type'] ?? $payload['type'] ?? 'unknown',
            'payload' => $payload,
            'status' => 'pending',
        ]);

        try {
            $gateway = PaymentGatewayFactory::make($gatewaySlug);
            $result = $gateway->handleWebhook($payload);

            if (isset($result['payment_id']) && $result['status'] === 'completed') {
                $payment = Payment::find($result['payment_id']);

                if ($payment && $payment->status !== 'completed') {
                    $this->paymentService->markAsPaid($payment, $result);
                }
            }

            $webhookLog->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error("Webhook processing failed for {$gatewaySlug}", [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            $webhookLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }
}
