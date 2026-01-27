<?php

namespace App\Http\Controllers\Api\Organizations;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    /**
     * Search organizations for frontend select/autocomplete.
     * Returns id and name with a limit of 8.
     */
    public function search(Request $request)
    {
        $query = $request->get('search');

        $organizations = Organization::query()
            ->when($query, function ($q) use ($query) {
                $q->where('name', 'like', '%' . $query . '%');
            })
            ->select('id', 'name')
            ->limit(8)
            ->get();

        return response()->json($organizations);
    }

    /**
     * Display a listing of organizations with search, filters and pagination.
     */
    public function index(Request $request)
    {
        $query = $request->get('search');
        $type = $request->get('type');
        $verified = $request->get('verified');

        $organizations = Organization::query()
            ->when($query, function ($q) use ($query) {
                $q->where('name', 'like', '%' . $query . '%');
            })
            ->when($type, function ($q) use ($type) {
                $q->where('type', $type);
            })
            ->when($verified !== null, function ($q) use ($verified) {
                $q->where('verified', filter_var($verified, FILTER_VALIDATE_BOOLEAN));
            })
            ->paginate($request->get('per_page', 15));

        return response()->json($organizations);
    }

    /**
     * Display organization details and its members.
     */
    public function show(Organization $organization)
    {
        $organization->load(['members.user:id,name,email']);

        return response()->json($organization);
    }
}
