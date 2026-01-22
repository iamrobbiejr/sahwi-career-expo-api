<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ResetPasswordController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            $validated = $request->validate([
                'token' => 'required',
                'email' => 'required|email',
                'password' => ['required', 'confirmed', PasswordRule::defaults()],
            ]);

            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) use ($request) {
                    $user->forceFill([
                        'password' => bcrypt($password),
                    ])->save();

                    Log::channel('auth')->info('Password reset completed', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'ip' => $request->ip(),
                    ]);
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return response()->json([
                    'message' => __($status),
                ]);
            }

            Log::channel('auth')->warning('Password reset failed', [
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
                'message' => 'An unexpected error occurred while resetting your password.'
            ], 500);
        }
    }
}
