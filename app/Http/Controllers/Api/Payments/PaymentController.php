<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use App\Services\PaymentService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'registration_ids' => 'required|array|min:1',
            'registration_ids.*' => 'exists:event_registrations,id',
            'payment_gateway' => 'required|string|exists:payment_gateways,slug',
            'payment_method' => 'nullable|string|in:card,mobile_money,bank_transfer',
            'payment_phone' => 'required_if:payment_method,mobile_money|string',
            'return_url' => 'nullable|url',
            'cancel_url' => 'nullable|url',
        ]);

        // Verify all registrations belong to user and same event
        $registrations = EventRegistration::whereIn('id', $validated['registration_ids'])
            ->where(function ($query) use ($request) {
                $query->where('user_id', $request->user()->id)
                    ->orWhere('registered_by', $request->user()->id);
            })
            ->get();

        if ($registrations->count() !== count($validated['registration_ids'])) {
            return response()->json([
                'message' => 'Invalid registrations',
            ], 422);
        }

        // Check all registrations are for the same event
        if ($registrations->pluck('event_id')->unique()->count() > 1) {
            return response()->json([
                'message' => 'All registrations must be for the same event',
            ], 422);
        }

        // Check if already paid
        $paidRegistrations = $registrations->filter(function ($reg) {
            return $reg->isPaid();
        });

        if ($paidRegistrations->isNotEmpty()) {
            return response()->json([
                'message' => 'Some registrations are already paid',
                'paid_registration_ids' => $paidRegistrations->pluck('id'),
            ], 422);
        }

        $event = $registrations->first()->event;

        if (!$event->is_paid) {
            return response()->json([
                'message' => 'This is a free event',
            ], 422);
        }

        // Create payment
        $payment = $this->paymentService->createPayment(
            $event,
            $request->user(),
            $validated['registration_ids'],
            $validated['payment_gateway']
        );

        // Update payment with additional details
        $payment->update([
            'payment_method' => $validated['payment_method'] ?? null,
            'payment_phone' => $validated['payment_phone'] ?? null,
        ]);

        // Initialize payment with gateway
        try {
            $gateway = PaymentGatewayFactory::make($validated['payment_gateway']);

            $initializationData = $gateway->initializePayment($payment, [
                'return_url' => $validated['return_url'] ?? route('payments.callback'),
                'cancel_url' => $validated['cancel_url'] ?? route('payments.cancelled'),
                'payment_method' => $validated['payment_method'] ?? 'card',
                'phone' => $validated['payment_phone'] ?? null,
            ]);

            $payment->update([
                'status' => 'processing',
                'gateway_response' => array_merge(
                    $payment->gateway_response ?? [],
                    ['initialization' => $initializationData]
                ),
            ]);

            return response()->json([
                'message' => 'Payment initiated successfully',
                'payment' => $payment->fresh(),
                'gateway_data' => $initializationData,
            ]);

        } catch (Exception $e) {
            $payment->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
                'failed_at' => now(),
            ]);

            return response()->json([
                'message' => 'Payment initialization failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Payment $payment): JsonResponse
    {
        // Authorization
        if ($payment->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $payment->load(['event', 'user', 'gateway', 'items.registration']);

        return response()->json([
            'payment' => $payment,
        ]);
    }

    public function status(Payment $payment): JsonResponse
    {
        // Authorization
        if ($payment->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'status' => $payment->status,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at,
            'can_retry' => in_array($payment->status, ['failed', 'cancelled']),
        ]);
    }

    public function verify(Request $request, Payment $payment): JsonResponse
    {
        // Authorization
        if ($payment->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($payment->status === 'completed') {
            return response()->json([
                'message' => 'Payment already completed',
                'payment' => $payment,
            ]);
        }

        try {
            $gateway = PaymentGatewayFactory::make($payment->gateway->slug);
            $status = $gateway->verifyPayment($payment);

            if ($status['status'] === 'completed') {
                $this->paymentService->markAsPaid($payment, $status);

                return response()->json([
                    'message' => 'Payment verified successfully',
                    'payment' => $payment->fresh()->load('items.registration.ticket'),
                ]);
            }

            return response()->json([
                'message' => 'Payment verification pending',
                'status' => $status,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Payment verification failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function myPayments(Request $request): JsonResponse
    {
        $payments = Payment::where('user_id', $request->user()->id)
            ->with(['event', 'gateway', 'items.registration'])
            ->when($request->has('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($payments);
    }

    public function refund(Request $request, Payment $payment): JsonResponse
    {
        if ($payment->status !== 'completed') {
            return response()->json([
                'message' => 'Only completed payments can be refunded',
            ], 422);
        }

        $validated = $request->validate([
            'amount_cents' => 'sometimes|integer|min:1|max:' . $payment->amount_cents,
            'reason' => 'required|string|max:500',
        ]);

        $amountCents = $validated['amount_cents'] ?? $payment->amount_cents;

        try {
            $refund = $this->paymentService->processRefund(
                $payment,
                $amountCents,
                $validated['reason'],
                $request->user()
            );

            // Process refund with gateway
            $gateway = PaymentGatewayFactory::make($payment->gateway->slug);
            $gatewayRefund = $gateway->refundPayment($payment, $amountCents);

            $refund->update([
                'gateway_refund_id' => $gatewayRefund['refund_id'] ?? null,
                'status' => 'completed',
                'processed_at' => now(),
            ]);

            return response()->json([
                'message' => 'Refund processed successfully',
                'refund' => $refund,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Refund processing failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function refunds(Request $request): JsonResponse
    {
        $refunds = Refund::with(['payment.event', 'payment.user', 'processedBy'])
            ->when($request->has('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($refunds);
    }
}
