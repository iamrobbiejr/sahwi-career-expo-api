<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    /**
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
                    throw new \Exception('Unexpected value');
            }
            // Create user
            $validated['password'] = Hash::make($validated['password']);
            $user = User::create(array_filter($validated)); // Remove nulls safely
            // Assign role
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
        } catch (\Exception $e) {
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
