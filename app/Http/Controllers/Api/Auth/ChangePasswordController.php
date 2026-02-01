<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Throwable;

class ChangePasswordController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            $validated = $request->validate([
                'current_password' => 'required|string',
                'password' => 'required|string|min:8|confirmed',
            ]);

            if (!Hash::check($validated['current_password'], $request->user()->password)) {
                Log::channel('auth')->warning('Change password failed - incorrect current password', [
                    'user_id' => $request->user()->id,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'message' => 'The current password is incorrect.',
                ], 400);
            }

            $request->user()->update([
                'password' => Hash::make($validated['password']),
            ]);

            Log::channel('auth')->info('Password changed successfully', [
                'user_id' => $request->user()->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Password changed successfully.',
            ]);

        } catch (Throwable $e) {
            Log::channel('auth')->error('Change password exception', [
                'user_id' => $request->user()?->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'An unexpected error occurred while changing the password.'
            ], 500);
        }
    }
}
