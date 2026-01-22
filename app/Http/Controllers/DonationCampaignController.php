<?php

namespace App\Http\Controllers;

use App\Models\DonationCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DonationCampaignController extends Controller
{
    /**
     * Display a listing of campaigns
     */
    public function index(Request $request): JsonResponse
    {
        $query = DonationCampaign::with('creator:id,name,email');

        // Filter by active status
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        // Filter by creator
        if ($request->has('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        // Filter campaigns that haven't reached goal
        if ($request->boolean('not_reached_goal')) {
            $query->notReachedGoal();
        }

        // Search in title and description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        if (in_array($sortBy, ['created_at', 'title', 'goal_cents'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $campaigns = $query->paginate($request->get('per_page', 15));

        return response()->json($campaigns);
    }

    /**
     * Store a newly created campaign
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'goal_amount' => 'required|numeric|min:1', // In dollars
            'description' => 'required|string',
            'active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Convert dollars to cents
        $goalCents = (int) ($request->goal_amount * 100);

        $campaign = DonationCampaign::create([
            'title' => $request->title,
            'goal_cents' => $goalCents,
            'description' => $request->description,
            'active' => $request->get('active', true),
            'created_by' => auth()->id(),
        ]);

        $campaign->load('creator:id,name,email');

        return response()->json([
            'message' => 'Campaign created successfully',
            'data' => $campaign
        ], 201);
    }

    /**
     * Display the specified campaign
     */
    public function show(DonationCampaign $campaign): JsonResponse
    {
        $campaign->load([
            'creator:id,name,email',
            'completedDonations' => function($query) {
                $query->with('donor:id,name')
                    ->where('anonymous', false)
                    ->latest()
                    ->limit(10);
            }
        ]);

        return response()->json($campaign);
    }

    /**
     * Update the specified campaign
     */
    public function update(Request $request, DonationCampaign $campaign): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'goal_amount' => 'sometimes|required|numeric|min:1',
            'description' => 'sometimes|required|string',
            'active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only(['title', 'description', 'active']);

        // Convert goal_amount to cents if provided
        if ($request->has('goal_amount')) {
            $updateData['goal_cents'] = (int) ($request->goal_amount * 100);
        }

        $campaign->update($updateData);
        $campaign->load('creator:id,name,email');

        return response()->json([
            'message' => 'Campaign updated successfully',
            'data' => $campaign
        ]);
    }

    /**
     * Remove the specified campaign
     */
    public function destroy(DonationCampaign $campaign): JsonResponse
    {
        // Optionally check if campaign has donations before deleting
        if ($campaign->donations()->exists()) {
            return response()->json([
                'message' => 'Cannot delete campaign with existing donations. Consider deactivating instead.'
            ], 422);
        }

        $campaign->delete();

        return response()->json([
            'message' => 'Campaign deleted successfully'
        ]);
    }

    /**
     * Toggle campaign active status
     */
    public function toggleActive(DonationCampaign $campaign): JsonResponse
    {
        $campaign->update([
            'active' => !$campaign->active
        ]);

        return response()->json([
            'message' => $campaign->active ? 'Campaign activated' : 'Campaign deactivated',
            'data' => $campaign
        ]);
    }

    /**
     * Get campaign statistics
     */
    public function statistics(DonationCampaign $campaign): JsonResponse
    {
        $stats = [
            'campaign_id' => $campaign->id,
            'title' => $campaign->title,
            'goal_cents' => $campaign->goal_cents,
            'goal_amount' => $campaign->goal_amount,
            'raised_cents' => $campaign->raised_cents,
            'raised_amount' => $campaign->raised_amount,
            'remaining_cents' => $campaign->remaining_cents,
            'remaining_amount' => $campaign->remaining_amount,
            'progress_percentage' => $campaign->progress_percentage,
            'total_donations' => $campaign->donations_count,
            'unique_donors' => $campaign->donors_count,
            'goal_reached' => $campaign->hasReachedGoal(),
            'average_donation_cents' => $campaign->donations_count > 0
                ? (int) ($campaign->raised_cents / $campaign->donations_count)
                : 0,
            'average_donation_amount' => $campaign->donations_count > 0
                ? round($campaign->raised_amount / $campaign->donations_count, 2)
                : 0,
        ];

        return response()->json($stats);
    }

    /**
     * Get top donors for a campaign
     */
    public function topDonors(DonationCampaign $campaign, Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);

        $topDonors = $campaign->completedDonations()
            ->with('donor:id,name')
            ->where('anonymous', false)
            ->selectRaw('donor_id, donor_name, SUM(amount_cents) as total_cents, COUNT(*) as donation_count')
            ->groupBy('donor_id', 'donor_name')
            ->orderByDesc('total_cents')
            ->limit($limit)
            ->get()
            ->map(function($donation) {
                return [
                    'donor_id' => $donation->donor_id,
                    'donor_name' => $donation->donor ? $donation->donor->name : $donation->donor_name,
                    'total_cents' => $donation->total_cents,
                    'total_amount' => $donation->total_cents / 100,
                    'donation_count' => $donation->donation_count,
                ];
            });

        return response()->json([
            'campaign_id' => $campaign->id,
            'top_donors' => $topDonors
        ]);
    }

    /**
     * Get recent donations for a campaign
     */
    public function recentDonations(DonationCampaign $campaign, Request $request): JsonResponse
    {
        $donations = $campaign->completedDonations()
            ->with('donor:id,name')
            ->latest('completed_at')
            ->paginate($request->get('per_page', 20));

        return response()->json($donations);
    }
}
