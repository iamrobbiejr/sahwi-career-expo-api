<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Exception;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     summary="User Registration",
     *     description="Register a new user account. Different roles require different fields. Students are auto-verified, while other roles require admin approval.",
     *     operationId="register",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password","name","role"},
     *             @OA\Property(property="email", type="string", format="email", example="newuser@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="SecurePass123!"),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="role", type="string", enum={"admin","student","professional","company_rep","university","single"}, example="student"),
     *             @OA\Property(property="current_school_name", type="string", example="ABC High School", description="Required for students"),
     *             @OA\Property(property="current_grade", type="string", example="Grade 12", description="Required for students"),
     *             @OA\Property(property="dob", type="string", format="date", example="2005-01-15"),
     *             @OA\Property(property="bio", type="string", example="Passionate about technology"),
     *             @OA\Property(property="organisation_id", type="string", example="uuid-here"),
     *             @OA\Property(property="title", type="string", example="Software Engineer"),
     *             @OA\Property(property="whatsapp_number", type="string", example="+1234567890"),
     *             @OA\Property(property="interested_area", type="string", example="Computer Science", description="Required for students"),
     *             @OA\Property(property="interested_course", type="string", example="BSc Computer Science", description="Required for students"),
     *             @OA\Property(property="expert_field", type="string", example="Data Science", description="Required for professionals"),
     *             @OA\Property(property="interested_university_id", type="integer", example=1, description="Required for students"),
     *             @OA\Property(property="role_at_organization", type="string", example="Manager"),
     *             @OA\Property(property="organization_name", type="string", example="Tech Corp"),
     *             @OA\Property(property="verification_docs", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="professional_verification_docs", type="array", @OA\Items(type="string"), description="Required for professionals")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Registration successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Registration successful. Please check your email to verify your account."),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="newuser@example.com"),
     *                 @OA\Property(property="role", type="string", example="student")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The email has already been taken.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred during registration. Please try again.")
     *         )
     *     )
     * )
     *
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            // Base validation
            $validated = $request->validate([
                'email' => 'required|email|unique:users,email',
                'password' => ['required', Password::defaults()],
                'name' => 'required|string|max:255',
                'role' => 'required|in:admin,student,professional,company_rep,university,single',

                // Optional general fields
                'current_school_name' => 'nullable|string|max:255',
                'current_grade' => 'nullable|string|max:50',
                'dob' => 'nullable|date',
                'bio' => 'nullable|string',
                'organisation_id' => 'nullable|string', // Temporarily string to allow UUID or name
                'title' => 'nullable|string|max:255',
                'whatsapp_number' => 'nullable|string|max:20',
                'interested_area' => 'nullable|string|max:255',
                'interested_course' => 'nullable|string|max:255',
                'expert_field' => 'nullable|string|max:255',
                'interested_university_id' => 'nullable|exists:universities,id',
                'role_at_organization' => 'nullable|string|max:100',
                'organization_name' => 'nullable|string|max:255',
                'verification_docs' => 'nullable|array',
                'professional_verification_docs' => 'nullable|array',
                // Avatar upload (optional)
                'avatar' => 'nullable|image|max:5120', // max 5MB
            ]);
            // Role-specific validation & processing
            switch ($validated['role']) {
                case 'student':
                    $this->validateStudentFields($request);
                    break;
                case 'professional':
                    $this->validateProfessionalFields($request);
                    break;
                case 'company_rep':
                    $validated['organisation_id'] = $this->handleOrganizationCreation(
                        $request,
                        'company',
                        $validated['organisation_id'] ?? null,
                        $validated['organization_name'] ?? null,
                        $validated['verification_docs'] ?? []
                    );
                    break;
                case 'university':
                    $validated['organisation_id'] = $this->handleOrganizationCreation(
                        $request,
                        'university',
                        $validated['organisation_id'] ?? null,
                        $validated['organization_name'] ?? null,
                        $validated['verification_docs'] ?? []
                    );
                    break;
                default:
                    throw new Exception('Unexpected value');
            }
            // Handle avatar upload (optional)
            if ($request->hasFile('avatar')) {
                $path = $request->file('avatar')->store('avatars', 'public');

                // Generate the URL including the full app/public path
                $validated['avatar_url'] = asset('storage/app/public/' . $path);
            }

            if ($validated['role'] === 'student') {
                $validated['verified'] = true;
                $validated['verification_submitted_at'] = null;
                $validated['verification_reviewed_at'] = now(); // Auto-approve students
            } else {
                $validated['verified'] = false;
                $validated['verification_reviewed_at'] = null;
                $validated['verification_submitted_at'] = now();
            }

            // Create user
            $validated['password'] = Hash::make($validated['password']);
            $user = User::create(array_filter($validated)); // Remove nulls safely
            // Assign a role
            $user->assignRole($validated['role']);
            // Link user to organization if applicable
            if (isset($validated['organisation_id'])) {
                OrganizationMember::create([
                    'organization_id' => $validated['organisation_id'],
                    'user_id' => $user->id,
                    'role' => $validated['role_at_organization'] ?? 'member',
                ]);
            }
            // Trigger email verification
            event(new Registered($user));
            return response()->json([
                'message' => 'Registration successful. Please check your email to verify your account.',
                'user' => $user
            ], 201);
        } catch (Exception $e) {
            Log::channel('auth')->error("Registration failed: {$e->getMessage()}", [
                'input' => $request->all(),
                'exception' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'An unexpected error occurred during registration. Please try again.'
            ], 500);
        }
    }
    private function validateStudentFields(Request $request)
    {
        $request->validate([
            'interested_university_id' => 'required|exists:universities,id',
            'current_school_name' => 'required|string|max:255',
            'current_grade' => 'required|string|max:50',
            'interested_area' => 'required|string|max:255',
            'interested_course' => 'required|string|max:255',
        ]);
    }
    private function validateProfessionalFields(Request $request)
    {
        $request->validate([
            'expert_field' => 'required|string|max:255',
            'professional_verification_docs' => 'required|array',
        ]);
    }
    private function handleOrganizationCreation(
        Request $request,
        string $expectedType,
        ?string $orgId,
        ?string $orgName,
        array $docs
    ): string {
        if ($orgId && Organization::where('id','=', $orgId)->where('type', $expectedType)->exists()) {
            return $orgId;
        }
        if (!$orgName) {
            abort(422, "Organization name is required.");
        }
        $request->validate([
            'organization_name' => 'required|string|max:255',
            'verification_docs' => 'array'
        ]);
        $organization = Organization::create([
            'name' => $orgName,
            'type' => $expectedType,
            'verified' => false,
            'verification_docs' => $docs,
        ]);
        return $organization->id;
    }
}
