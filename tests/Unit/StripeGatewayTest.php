<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Services\PaymentGateways\Stripe\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use ReflectionClass;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentGateway::create([
            'name' => 'Stripe',
            'slug' => 'stripe',
            'is_active' => true,
            'supports_webhooks' => true,
            'credentials' => [
                'secret' => 'sk_test_123',
            ],
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
        $gateway = PaymentGateway::where('slug', 'stripe')->first();

        return Payment::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'payment_gateway_id' => $gateway->id,
            'gateway_name' => 'stripe',
            'amount_cents' => 10000,
            'currency' => 'USD',
            'status' => 'pending',
        ]);
    }

    public function test_initialize_payment_returns_authorization_url()
    {
        $payment = $this->makePayment();

        // Mock Stripe Client
        $mockSession = (object)[
            'id' => 'sess_123',
            'url' => 'https://checkout.stripe.com/pay/sess_123',
            'payment_intent' => 'pi_123'
        ];

        $mockStripe = Mockery::mock(StripeClient::class);
        $mockStripe->checkout = Mockery::mock();
        $mockStripe->checkout->sessions = Mockery::mock();
        $mockStripe->checkout->sessions->shouldReceive('create')
            ->once()
            ->andReturn($mockSession);

        $this->app->instance(StripeClient::class, $mockStripe);

        $gateway = new StripeGateway();
        // Overwrite the stripe property because it's initialized in constructor
        $reflection = new ReflectionClass($gateway);
        $property = $reflection->getProperty('stripe');
        $property->setAccessible(true);
        $property->setValue($gateway, $mockStripe);

        $res = $gateway->initializePayment($payment, [
            'return_url' => 'http://localhost/return',
        ]);

        $this->assertEquals('sess_123', $res['session_id']);
        $this->assertEquals('https://checkout.stripe.com/pay/sess_123', $res['authorization_url']);
        $this->assertEquals('https://checkout.stripe.com/pay/sess_123', $res['checkout_url']);
    }

    public function test_verify_payment_maps_paid_to_completed()
    {
        $payment = $this->makePayment();
        $payment->update([
            'gateway_response' => [
                'initialization' => ['session_id' => 'sess_123']
            ]
        ]);

        $mockSession = (object)[
            'payment_status' => 'paid',
            'payment_intent' => 'pi_123',
            'amount_total' => 10000,
            'toArray' => function () {
                return [];
            }
        ];
        // Wait, toArray is usually a method.
        $mockSession = Mockery::mock();
        $mockSession->payment_status = 'paid';
        $mockSession->payment_intent = 'pi_123';
        $mockSession->amount_total = 10000;
        $mockSession->shouldReceive('toArray')->andReturn(['id' => 'sess_123']);

        $mockStripe = Mockery::mock(StripeClient::class);
        $mockStripe->checkout = Mockery::mock();
        $mockStripe->checkout->sessions = Mockery::mock();
        $mockStripe->checkout->sessions->shouldReceive('retrieve')
            ->with('sess_123')
            ->once()
            ->andReturn($mockSession);

        $gateway = new StripeGateway();
        $reflection = new ReflectionClass($gateway);
        $property = $reflection->getProperty('stripe');
        $property->setAccessible(true);
        $property->setValue($gateway, $mockStripe);

        $res = $gateway->verifyPayment($payment);

        $this->assertEquals('completed', $res['status']);
        $this->assertEquals('pi_123', $res['transaction_id']);
        $this->assertEquals(100, $res['amount']);
    }
}
