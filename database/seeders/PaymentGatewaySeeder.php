<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        // ------------------------------------------------------------------
        // SahwiPay (Sahwi.net Visa/Mastercard redirect gateway)
        // ------------------------------------------------------------------
        PaymentGateway::updateOrCreate(
            ['slug' => 'sahwipay'],
            [
                'name' => 'SahwiPay',
                'is_active' => true,
                'display_order' => 2,
                'supports_webhooks' => false,   // Status returned via redirect URL params
                'supported_currencies' => ['USD'],
                'credentials' => [],       // No API credentials; it's a public redirect
                'settings' => [
                    'base_url' => 'https://pay.sahwi.net/#/visa-mastercard/',
                    'txn_source' => 'SEAL_TICKETS_WEB_APP',
                ],
            ]
        );

        // ------------------------------------------------------------------
        // Paynow
        // ------------------------------------------------------------------
        PaymentGateway::updateOrCreate(
            ['slug' => 'paynow'],
            [
                'name' => 'Paynow',
                'is_active' => true,
                'display_order' => 1,
                'supports_webhooks' => true,
                'supported_currencies' => ['USD', 'ZIG'],
                'credentials' => [
                    // Foreign (USD) integration — used for mobile money Express payments
                    'foreign_integration_id' => env('PAYNOW_FOREIGN_INTEGRATION_ID', ''),
                    'foreign_integration_key' => env('PAYNOW_FOREIGN_INTEGRATION_KEY', ''),
                    // Local (ZIG) integration — used for redirect / standard payments
                    'local_integration_id' => env('PAYNOW_LOCAL_INTEGRATION_ID', ''),
                    'local_integration_key' => env('PAYNOW_LOCAL_INTEGRATION_KEY', ''),
                ],
                'settings' => [
                    'initiate_url' => env(
                        'PAYNOW_INITIATE_TRANSACTION_URL',
                        'https://www.paynow.co.zw/interface/initiatetransaction'
                    ),
                ],
            ]
        );

        // ------------------------------------------------------------------
        // Stripe
        // ------------------------------------------------------------------
        PaymentGateway::updateOrCreate(
            ['slug' => 'stripe'],
            [
                'name' => 'Stripe',
                'is_active' => true,
                'display_order' => 2,
                'supports_webhooks' => true,
                'supported_currencies' => ['USD'],
                'credentials' => [
                    'key' => env('STRIPE_KEY', ''),
                    'secret' => env('STRIPE_SECRET', ''),
                    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
                ],
            ]
        );

        // ------------------------------------------------------------------
        // Smile & Pay (ZB Pay)
        // ------------------------------------------------------------------
        PaymentGateway::updateOrCreate(
            ['slug' => 'smile-and-pay'],
            [
                'name' => 'Smile & Pay',
                'is_active' => true,
                'display_order' => 3,
                'supports_webhooks' => true,
                'supported_currencies' => ['USD', 'ZIG'],
                'credentials' => [
                    'base_url' => env('SMILEPAY_BASE_URL', 'https://zbnet.zb.co.zw/wallet_sandbox_api/payments-gateway'),
                    'api_key' => env('SMILEPAY_API_KEY', ''),
                    'api_secret' => env('SMILEPAY_API_SECRET', ''),
                ],
            ]
        );
    }
}
