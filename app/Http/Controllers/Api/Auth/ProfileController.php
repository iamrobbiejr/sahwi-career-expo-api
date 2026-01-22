<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user());
    }
    public function update(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'display_name' => 'sometimes|string|max:255',
            'avatar_url' => 'nullable|url',
            'current_school_name' => 'nullable|string|max:255',
            'current_grade' => 'nullable|string|max:50',
            'dob' => 'nullable|date',
            'bio' => 'nullable|string',
            'organisation_id' => 'nullable|exists:organizations,id',
            'title' => 'nullable|string|max:255',
            'whatsapp_number' => 'nullable|string|max:20',
            'interested_area' => 'nullable|string|max:255',
            'interested_course' => 'nullable|string|max:255',
        ]);
        $user->update($validated);
        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $user->fresh()
        ]);
    }
}
