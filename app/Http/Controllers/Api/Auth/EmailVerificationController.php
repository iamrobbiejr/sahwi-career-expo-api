<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailVerificationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(EmailVerificationRequest $request)
    {
        try {
            $user = $request->user();

            if ($user->hasVerifiedEmail()) {
                Log::channel('auth')->info('Email verification skipped: already verified', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'message' => 'Email already verified.'
                ], 400);
            }

            if ($user->markEmailAsVerified()) {
                event(new Verified($user));

                Log::channel('auth')->info('Email verified successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip(),
                ]);
            }

            return response()->json([
                'message' => 'Email verified successfully'
            ]);

        } catch (\Throwable $e) {
            Log::channel('auth')->error('Email verification exception', [
                'user_id' => optional($request->user())->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'An unexpected error occurred while verifying your email.'
            ], 500);
        }
    }
}
