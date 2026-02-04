<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailVerificationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $id, $hash)
    {
        try {
            $user = User::findOrFail($id);

            // Validate hash
            if (!hash_equals(
                sha1($user->getEmailForVerification()),
                $hash
            )) {
                abort(403, 'Invalid verification link.');
            }

            if ($user->hasVerifiedEmail()) {
                Log::channel('auth')->info('Email already verified', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip(),
                ]);

                return view('auth.email-verified', [
                    'alreadyVerified' => true
                ]);
            }

            $user->markEmailAsVerified();
            event(new Verified($user));

            Log::channel('auth')->info('Email verified successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
            ]);

            return view('auth.email-verified', [
                'alreadyVerified' => false
            ]);

        } catch (Throwable $e) {
            Log::channel('auth')->error('Email verification exception', [
                'user_id' => $id ?? null,
                'message' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return view('auth.email-verification-failed');
        }
    }
}
