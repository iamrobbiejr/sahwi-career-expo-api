<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentGatewayController extends Controller
{
    public function index(): JsonResponse
    {
        $gateways = PaymentGateway::orderBy('display_order')
            ->get()
            ->map(function ($gateway) {
                return [
                    'id' => $gateway->id,
                    'name' => $gateway->name,
                    'slug' => $gateway->slug,
                    'supports_webhooks' => $gateway->supports_webhooks,
                    'supported_currencies' => $gateway->supported_currencies,
                    'is_active' => $gateway->is_active,
                    'display_order' => $gateway->display_order,
                ];
            });

        return response()->json($gateways);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:payment_gateways,slug',
            'is_active' => 'boolean',
            'display_order' => 'integer|min:0',
            'credentials' => 'required|array',
            'settings' => 'nullable|array',
            'supports_webhooks' => 'boolean',
            'webhook_secret' => 'nullable|string',
            'supported_currencies' => 'nullable|array',
        ]);

        $gateway = PaymentGateway::create($validated);

        return response()->json([
            'message' => 'Payment gateway created successfully',
            'gateway' => $gateway,
        ], 201);
    }

    public function show(PaymentGateway $paymentGateway): JsonResponse
    {
        return response()->json([
            'id' => $paymentGateway->id,
            'name' => $paymentGateway->name,
            'slug' => $paymentGateway->slug,
            'supports_webhooks' => $paymentGateway->supports_webhooks,
            'supported_currencies' => $paymentGateway->supported_currencies,
            'is_active' => $paymentGateway->is_active,
            'display_order' => $paymentGateway->display_order,
        ]);
    }

    public function update(Request $request, PaymentGateway $paymentGateway): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|unique:payment_gateways,slug,' . $paymentGateway->id,
            'is_active' => 'boolean',
            'display_order' => 'integer|min:0',
            'supports_webhooks' => 'boolean',
        ]);

        $paymentGateway->update($validated);

        return response()->json([
            'message' => 'Payment gateway updated successfully',
            'gateway' => [
                'id' => $paymentGateway->id,
                'name' => $paymentGateway->name,
                'slug' => $paymentGateway->slug,
                'supports_webhooks' => $paymentGateway->supports_webhooks,
                'supported_currencies' => $paymentGateway->supported_currencies,
                'is_active' => $paymentGateway->is_active,
                'display_order' => $paymentGateway->display_order,
            ],
        ]);
    }

    public function destroy(PaymentGateway $paymentGateway): JsonResponse
    {
        // Check if gateway has payments
        if ($paymentGateway->payments()->exists()) {
            return response()->json([
                'message' => 'Cannot delete gateway with existing payments',
            ], 422);
        }

        $paymentGateway->delete();

        return response()->json([
            'message' => 'Payment gateway deleted successfully',
        ]);
    }
}
