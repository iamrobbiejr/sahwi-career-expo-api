<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = User::query();
            // Filters
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('email', 'LIKE', "%{$search}%")
                        ->orWhere('display_name', 'LIKE', "%{$search}%");
                });
            }
            if ($role = $request->get('role')) {
                $query->where('role', $role);
            }
            if ($verified = $request->get('verified')) {
                $query->where('verified', filter_var($verified, FILTER_VALIDATE_BOOLEAN));
            }
            $users = $query->paginate($request->get('per_page', 15));
            return response()->json([
                'message' => 'Users retrieved successfully.',
                'data' => $users->items(),
                'pagination' => [
                    'total' => $users->total(),
                    'count' => $users->count(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'total_pages' => $users->lastPage(),
                ],
            ]);
        } catch (Exception $e) {
            Log::channel('admin')->error("Error fetching users: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
                'filters' => $request->all()
            ]);
            return response()->json(['message' => 'Failed to fetch users'], 500);
        }
    }
    public function show(string $userId)
    {
        try {
            $user = User::findOrFail($userId);
            return response()->json([
                'message' => 'User details retrieved.',
                'data' => $user
            ]);
        } catch (Exception $e) {
            Log::channel('admin')->error("Error fetching user ID {$userId}: {$e->getMessage()}");
            return response()->json(['message' => 'User not found'], 404);
        }
    }
    public function updateRole(string $userId, Request $request)
    {
        try {
            $request->validate(['role' => 'required|exists:roles,name']);
            $user = User::findOrFail($userId);
            $newRole = $request->input('role');
            $user->syncRoles([$newRole]);
            return response()->json([
                'message' => 'User role updated successfully.',
                'user' => $user->fresh()
            ]);
        } catch (Exception $e) {
            Log::channel('admin')->error("Error updating user role ID {$userId}: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to update user role'], 500);
        }
    }
    public function toggleSuspension(string $userId)
    {
        try {
            $user = User::findOrFail($userId);
            if ($user->deleted_at) {
                $user->restore();
                return response()->json(['message' => 'User unsuspended.']);
            } else {
                $user->delete();
                return response()->json(['message' => 'User suspended.']);
            }
        } catch (Exception $e) {
            Log::channel('admin')->error("Error toggling suspension for user ID {$userId}: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to update user suspension status'], 500);
        }
    }
    public function destroy(string $userId)
    {
        try {
            $user = User::findOrFail($userId);
            $user->forceDelete();
            return response()->json(['message' => 'User permanently deleted.']);
        } catch (Exception $e) {
            Log::channel('admin')->error("Error deleting user ID {$userId}: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to delete user'], 500);
        }
    }
}
