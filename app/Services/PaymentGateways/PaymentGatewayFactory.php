<?php

namespace App\Services\PaymentGateways;

use App\Services\PaymentGateways\Contracts\PaymentGatewayInterface;
use App\Services\PaymentGateways\Paynow\PaynowGateway;
use App\Services\PaymentGateways\Paypal\PaypalGateway;
use App\Services\PaymentGateways\Stripe\StripeGateway;

class PaymentGatewayFactory
{
    public static function make(string $gateway): PaymentGatewayInterface
    {
        return match ($gateway) {
            'paynow' => app(PaynowGateway::class),
            'paypal' => app(PaypalGateway::class),
            'stripe' => app(StripeGateway::class),
            default => throw new \Exception("Unsupported payment gateway: {$gateway}"),
        };
    }
}
