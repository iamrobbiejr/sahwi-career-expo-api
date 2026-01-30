<?php

namespace App\Http\Controllers\Api\Me;

use App\Http\Controllers\Controller;
use App\Models\UserReward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RewardsHistoryController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = Auth::user();

        $query = UserReward::where('user_id', $user->id)
            ->orderBy('awarded_at', 'desc');

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('from')) {
            $query->whereDate('award_date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('award_date', '<=', $request->input('to'));
        }

        $perPage = (int)$request->input('per_page', 20);

        $history = $query->paginate($perPage);

        return response()->json($history);
    }
}
