<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use App\Models\DonationCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DonationController extends Controller
{
    /**
     * Display a listing of donations
     */
    public function index(Request $request): JsonResponse
    {
        $query = Donation::with(['campaign:id,title', 'donor:id,name']);

        // Filter by campaign
        if ($request->has('campaign_id')) {
            $query->where('campaign_id', $request->campaign_id);
        }

        // Filter by donor
        if ($request->has('donor_id')) {
            $query->where('donor_id', $request->donor_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by payment method
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Date range filter
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Exclude anonymous donations from public view
        if ($request->boolean('exclude_anonymous')) {
            $query->where('anonymous', false);
        }

        $donations = $query->latest('completed_at')
            ->paginate($request->get('per_page', 20));

        return response()->json($donations);
    }

    /**
     * Store a newly created donation
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'required|exists:donation_campaigns,id',
            'amount' => 'required|numeric|min:1', // In dollars
            'donor_name' => 'nullable|string|max:255',
            'donor_email' => 'nullable|email|max:255',
            'message' => 'nullable|string|max:1000',
            'payment_method' => 'nullable|string|in:credit_card,debit_card,paypal,bank_transfer,mobile_money,other',
            'anonymous' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if campaign is active
        $campaign = DonationCampaign::find($request->campaign_id);
        if (!$campaign->active) {
            return response()->json([
                'message' => 'This campaign is not currently accepting donations'
            ], 422);
        }

        // Convert dollars to cents
        $amountCents = (int) ($request->amount * 100);

        $donation = Donation::create([
            'campaign_id' => $request->campaign_id,
            'donor_id' => auth()->id(), // Will be null if not authenticated
            'amount_cents' => $amountCents,
            'donor_name' => $request->donor_name,
            'donor_email' => $request->donor_email,
            'message' => $request->message,
            'payment_method' => $request->payment_method ?? 'other',
            'anonymous' => $request->get('anonymous', false),
            'status' => 'pending',
            'transaction_id' => 'TXN-' . strtoupper(Str::random(12)), // Generate unique transaction ID
        ]);

        // In a real application, you would integrate with a payment gateway here
        // For now, we'll auto-complete the donation (for testing purposes)
        // Remove this in production and implement proper payment processing
        $donation->markAsCompleted();

        $donation->load(['campaign:id,title', 'donor:id,name']);

        return response()->json([
            'message' => 'Donation created successfully',
            'data' => $donation
        ], 201);
    }

    /**
     * Display the specified donation
     */
    public function show(Donation $donation): JsonResponse
    {
        $donation->load(['campaign:id,title,goal_cents', 'donor:id,name']);

        return response()->json($donation);
    }

    /**
     * Update the specified donation
     */
    public function update(Request $request, Donation $donation): JsonResponse
    {
        // Only allow updating if donation is still pending
        if ($donation->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending donations can be updated'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'sometimes|required|numeric|min:1',
            'donor_name' => 'nullable|string|max:255',
            'donor_email' => 'nullable|email|max:255',
            'message' => 'nullable|string|max:1000',
            'anonymous' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only(['donor_name', 'donor_email', 'message', 'anonymous']);

        if ($request->has('amount')) {
            $updateData['amount_cents'] = (int) ($request->amount * 100);
        }

        $donation->update($updateData);

        return response()->json([
            'message' => 'Donation updated successfully',
            'data' => $donation
        ]);
    }

    /**
     * Cancel/Delete a pending donation
     */
    public function destroy(Donation $donation): JsonResponse
    {
        // Only allow deletion of pending donations
        if ($donation->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending donations can be cancelled'
            ], 422);
        }

        $donation->delete();

        return response()->json([
            'message' => 'Donation cancelled successfully'
        ]);
    }

    /**
     * Update donation status (admin/system use)
     */
    public function updateStatus(Request $request, Donation $donation): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,completed,failed,refunded',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = ['status' => $request->status];

        if ($request->status === 'completed' && !$donation->completed_at) {
            $updateData['completed_at'] = now();
        }

        $donation->update($updateData);

        return response()->json([
            'message' => 'Donation status updated successfully',
            'data' => $donation
        ]);
    }

    /**
     * Process payment for a donation (simulate payment processing)
     */
    public function processPayment(Request $request, Donation $donation): JsonResponse
    {
        if ($donation->status !== 'pending') {
            return response()->json([
                'message' => 'This donation has already been processed'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|string',
            'transaction_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // In a real application, integrate with payment gateway here
        // For now, we'll simulate successful payment

        $donation->update([
            'payment_method' => $request->payment_method,
            'transaction_id' => $request->transaction_id ?? $donation->transaction_id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Payment processed successfully',
            'data' => $donation
        ]);
    }

    /**
     * Get donation statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $query = Donation::query();

        // Filter by date range if provided
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $stats = [
            'total_donations' => $query->completed()->count(),
            'total_raised_cents' => $query->completed()->sum('amount_cents'),
            'total_raised_amount' => $query->completed()->sum('amount_cents') / 100,
            'pending_donations' => Donation::pending()->count(),
            'failed_donations' => Donation::where('status', 'failed')->count(),
            'average_donation_cents' => (int) $query->completed()->avg('amount_cents'),
            'average_donation_amount' => round($query->completed()->avg('amount_cents') / 100, 2),
            'largest_donation_cents' => $query->completed()->max('amount_cents'),
            'largest_donation_amount' => $query->completed()->max('amount_cents') / 100,
            'unique_donors' => $query->completed()->whereNotNull('donor_id')->distinct('donor_id')->count('donor_id'),
        ];

        return response()->json($stats);
    }

    /**
     * Get user's donation history
     */
    public function myDonations(Request $request): JsonResponse
    {
        if (!auth()->check()) {
            return response()->json([
                'message' => 'Authentication required'
            ], 401);
        }

        $donations = Donation::where('donor_id', auth()->id())
            ->with('campaign:id,title,goal_cents')
            ->latest('completed_at')
            ->paginate($request->get('per_page', 20));

        $summary = [
            'total_donations' => Donation::where('donor_id', auth()->id())->completed()->count(),
            'total_donated_cents' => Donation::where('donor_id', auth()->id())->completed()->sum('amount_cents'),
            'total_donated_amount' => Donation::where('donor_id', auth()->id())->completed()->sum('amount_cents') / 100,
            'campaigns_supported' => Donation::where('donor_id', auth()->id())
                ->completed()
                ->distinct('campaign_id')
                ->count('campaign_id'),
        ];

        return response()->json([
            'summary' => $summary,
            'donations' => $donations
        ]);
    }
}
