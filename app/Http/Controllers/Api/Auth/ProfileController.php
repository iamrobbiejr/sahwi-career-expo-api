<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user());
    }
    public function update(Request $request)
    {
        try {
            $user = $request->user();


            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $user->id,
                'avatar' => 'sometimes|file|image|max:2048',
                'dob' => 'sometimes|date',
            ]);

            if ($request->hasFile('avatar')) {
                $path = $request->file('avatar')->store('avatars', 'public');
                $validated['avatar_url'] = asset('storage/app/public/' . $path);
            }
            if (isset($validated['dob'])) {
                $validated['dob'] = Carbon::parse($validated['dob'])->format('Y-m-d');
            }


            $user->update($validated);

            Log::channel('auth')->info('Profile updated successfully', [
                'user_id' => $user->id,
                'changes' => array_keys($validated),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Profile updated successfully',
                'data' => $user->fresh()
            ]);

        } catch (Throwable $e) {
            Log::channel('auth')->error('Profile update exception', [
                'user_id' => $request->user()?->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'An unexpected error occurred while updating the profile.'
            ], 500);
        }
    }
}
