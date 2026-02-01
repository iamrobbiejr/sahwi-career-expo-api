<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Services\PaymentGateways\SmilePay\SmilePayGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmilePayGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentGateway::create([
            'name' => 'Smile&Pay',
            'slug' => 'smile-and-pay',
            'is_active' => true,
            'supports_webhooks' => true,
            'credentials' => [
                'merchant_id' => 'MID123',
                'api_key' => 'key_123',
            ],
            'settings' => [
                'return_url' => 'https://example.com/return',
                'cancel_url' => 'https://example.com/cancel',
                'failure_url' => 'https://example.com/failure',
            ],
            'webhook_url' => 'https://example.com/webhook',
        ]);
    }

    protected function makePayment(): Payment
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'name' => 'Test Event',
            'created_by' => $user->id,
            'is_paid' => true,
            'price_cents' => 10000,
            'currency' => 'USD',
        ]);
        $gateway = PaymentGateway::where('slug', 'smile-and-pay')->first();

        return Payment::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'payment_gateway_id' => $gateway->id,
            'gateway_name' => 'smile-and-pay',
            'amount_cents' => 10000,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'innbucks',
        ]);
    }

    public function test_initiate_returns_payment_url_and_sets_processing()
    {
        $payment = $this->makePayment();

        Http::fake([
            'zbnet.zb.co.zw/*' => Http::response([
                'responseMessage' => 'OK',
                'responseCode' => '00',
                'status' => 'PENDING',
                'transactionReference' => 'TX123',
                'orderReference' => $payment->payment_reference,
                'paymentUrl' => 'https://pay.example.com/checkout/XYZ',
            ], 200),
        ]);

        $gateway = app(SmilePayGateway::class);
        $res = $gateway->initializePayment($payment, []);

        $this->assertEquals('https://pay.example.com/checkout/XYZ', $res['paymentUrl']);
        $payment->refresh();
        $this->assertEquals('processing', $payment->status);
    }

    public function test_verify_payment_maps_paid_to_completed()
    {
        $payment = $this->makePayment();
        $payment->update(['gateway_transaction_id' => 'TX123']);

        Http::fake([
            'zbnet.zb.co.zw/*/payments/transaction/*/status/check' => Http::response([
                'orderReference' => $payment->payment_reference,
                'reference' => 'TX123',
                'status' => 'SUCCESS',
            ], 200),
        ]);

        $gateway = app(SmilePayGateway::class);
        $res = $gateway->verifyPayment($payment);

        $this->assertEquals('completed', $res['status']);
    }

    public function test_webhook_paid_returns_completed_without_updating_model()
    {
        $payment = $this->makePayment();

        $gateway = app(SmilePayGateway::class);
        $result = $gateway->handleWebhook([
            'orderReference' => $payment->payment_reference,
            'transactionReference' => 'TX999',
            'status' => 'SUCCESS',
        ]);

        $this->assertEquals('completed', $result['status']);
        $payment->refresh();
        // Status should remain unchanged here; WebhookController marks paid centrally
        $this->assertNotEquals('completed', $payment->status);
    }
}
