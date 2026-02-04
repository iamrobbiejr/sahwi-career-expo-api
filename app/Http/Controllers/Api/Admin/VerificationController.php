<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PendingVerificationResource;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\UserVerificationStatusChanged;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerificationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);

            $users = User::whereIn('role', ['professional', 'company_rep', 'university'])
                ->where('verified', '=', 0)
                ->with('organisation')
                ->paginate($perPage);

            return response()->json([
                'message' => 'Pending verifications fetched.',
                'data' => PendingVerificationResource::collection($users->items()),
                'pagination' => [
                    'total' => $users->total(),
                    'count' => $users->count(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'total_pages' => $users->lastPage(),
                ],
            ]);
        } catch (Exception $e) {
            Log::channel('admin')->error("Failed fetching pending verifications: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'An error occurred while retrieving pending verifications.'
            ], 500);
        }
    }
    public function approve(string $userId)
    {
        try {
            $user = User::findOrFail($userId);
            Log::channel('admin')->info("Approving user ID {$userId}", [
                'user' => $user->toArray(),
            ]);
            if (!in_array($user->role->value, ['professional', 'company_rep', 'university'], true)) {
                return response()->json(['message' => 'User does not require manual verification.'], 400);
            }
            if ($user->verified) {
                return response()->json(['message' => 'User is already verified.'], 400);
            }
            // Mark user as verified
            $user->update([
                'verified' => true,
                'verification_reviewed_at' => now(),
            ]);
            // Verify associated organization if exists
            if ($user->organisation_id) {
                Organization::where('id', $user->organisation_id)->update(['verified' => true]);
            }
            // Notify user
            $user->notify(new UserVerificationStatusChanged(true));
            return response()->json([
                'message' => 'User and associated organization verified successfully.',
                'user' => $user->fresh()
            ]);
        } catch (Exception $e) {
            Log::channel('admin')->error("Failed approving user ID {$$userId}: {$$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'An error occurred while verifying the user.'
            ], 500);
        }
    }
    public function reject(string $userId, Request $request)
    {
        try {
            $user = User::findOrFail($userId);
            if (!in_array($user->role, ['professional', 'company_rep', 'university'])) {
                return response()->json(['message' => 'User does not require manual verification.'], 400);
            }
            if ($user->verified) {
                return response()->json(['message' => 'Cannot reject already verified user.'], 400);
            }
            $reason = $request->input('reason', 'Verification request denied.');
            // Update user with rejection timestamp
            $user->update([
                'verified' => false,
                'verification_reviewed_at' => now(),
            ]);
            // Notify user
            $user->notify(new UserVerificationStatusChanged(false, $reason));
            return response()->json([
                'message' => "User rejected. Reason: {$reason}",
                'user' => $user->fresh()
            ]);
        } catch (Exception $e) {
            Log::channel('admin')->error("Failed rejecting user ID {$$userId}: {$$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
                'reason' => $request->input('reason')
            ]);
            return response()->json([
                'message' => 'An error occurred while rejecting the user.'
            ], 500);
        }
    }
}
