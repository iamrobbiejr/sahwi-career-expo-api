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
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     summary="User Login",
     *     description="Authenticate a user and receive an access token. The user must have a verified email and approved verification status (for certain roles).",
     *     operationId="login",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logged in successfully."),
     *             @OA\Property(property="token", type="string", example="1|abcdef123456..."),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="user@example.com"),
     *                 @OA\Property(property="role", type="string", example="student")
     *             ),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid credentials")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Email not verified or verification pending/denied",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Please verify your email address before logging in.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred. Please try again later.")
     *         )
     *     )
     * )
     *
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


    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     summary="User Logout",
     *     description="Revoke all authentication tokens for the current user",
     *     operationId="logout",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logged out successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred while logging out.")
     *         )
     *     )
     * )
     */
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
