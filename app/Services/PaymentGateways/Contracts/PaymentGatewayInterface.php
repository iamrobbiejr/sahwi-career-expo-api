<?php

namespace App\Services\PaymentGateways\Contracts;

use App\Models\Payment;

interface PaymentGatewayInterface
{
    /**
     * Initialize a payment
     */
    public function initializePayment(Payment $payment, array $options = []): array;

    /**
     * Verify payment status
     */
    public function verifyPayment(Payment $payment): array;

    /**
     * Process a refund
     */
    public function refundPayment(Payment $payment, int $amountCents): array;

    /**
     * Handle webhook
     */
    public function handleWebhook(array $payload): array;
}
