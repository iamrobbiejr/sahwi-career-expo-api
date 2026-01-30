<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RewardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class LoginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            $credentials = $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            if (!Auth::attempt($credentials)) {
                Log::channel('auth')->warning('Login failed: invalid credentials', [
                    'email' => $credentials['email'],
                    'ip' => $request->ip(),
                ]);

                return response()->json(['message' => 'Invalid credentials'], 401);
            }

            /** @var User $user */
            $user = Auth::user();

            // Email isn't verified
            if (!$user->hasVerifiedEmail()) {
                Log::channel('auth')->notice('Login blocked: email not verified', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);

                Auth::logout();

                return response()->json([
                    'message' => 'Please verify your email address before logging in.'
                ], 403);
            }

            $restrictedRoles = ['professional', 'company_rep', 'university'];

            // Verification denied
            if (
                in_array($user->role->value, $restrictedRoles) &&
                !$user->verified &&
                !is_null($user->verification_reviewed_at)
            ) {
                Log::channel('auth')->notice('Login blocked: verification denied', [
                    'user_id' => $user->id,
                    'role' => $user->role->value,
                ]);

                Auth::logout();

                return response()->json([
                    'message' => 'Your verification request was denied. If you believe this is an error, please contact our help center.'
                ], 403);
            }

            // Pending verification
            if (
                in_array($user->role->value, $restrictedRoles) &&
                !$user->verified &&
                is_null($user->verification_reviewed_at)
            ) {
                Log::channel('auth')->info('Login blocked: verification pending', [
                    'user_id' => $user->id,
                    'role' => $user->role->value,
                ]);

                Auth::logout();

                return response()->json([
                    'message' => 'Your account is awaiting review by an administrator. Please check back later.'
                ], 403);
            }

            // Successful login
            $token = $user->createToken('api-token')->plainTextToken;

            Log::channel('auth')->info('Login successful', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
            ]);

            // Reward: daily login (once per day)
            try {
                app(RewardService::class)->awardFor($user, 'daily_login');
            } catch (Throwable $e) {
                // Non-critical
            }

            return response()->json([
                'message' => 'Logged in successfully.',
                'token' => $token,
                'user' => $user,
                'permissions' => $user->getPermissionsViaRoles()->pluck('name'),
            ]);

        } catch (Throwable $e) {
            Log::channel('auth')->error('Login exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'An unexpected error occurred. Please try again later.'
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                Log::channel('auth')->warning('Logout attempted without authenticated user', [
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            // Revoke all tokens
            $user->tokens()->delete();

            Log::channel('auth')->info('Logout successful', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Logged out successfully'
            ]);

        } catch (Throwable $e) {
            Log::channel('auth')->error('Logout exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'An unexpected error occurred while logging out.'
            ], 500);
        }
    }
}
