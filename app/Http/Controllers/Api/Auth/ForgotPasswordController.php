<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
            ]);

            $status = Password::sendResetLink([
                'email' => $validated['email'],
            ]);

            if ($status === Password::RESET_LINK_SENT) {
                Log::channel('auth')->info('Password reset link sent', [
                    'email' => $validated['email'],
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'message' => __($status),
                ]);
            }

            Log::channel('auth')->warning('Password reset link failed', [
                'email' => $validated['email'],
                'status' => $status,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => __($status),
            ], 400);

        } catch (\Throwable $e) {
            Log::channel('auth')->error('Password reset exception', [
                'email' => $request->input('email'),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'An unexpected error occurred while sending the password reset link.'
            ], 500);
        }
    }
}
