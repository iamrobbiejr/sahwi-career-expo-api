<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    /**
     * Display a listing of roles with their permissions.
     */
    public function indexRoles()
    {
        $roles = Role::with('permissions')->get();
        return response()->json($roles);
    }

    /**
     * Display a listing of all available permissions.
     */
    public function indexPermissions()
    {
        $permissions = Permission::all();
        return response()->json($permissions);
    }

    /**
     * Update the permissions for a specific role.
     */
    public function update(Request $request, Role $role)
    {
        $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name', // Validate permission names exist
        ]);

        // Sync permissions (this will replace existing permissions with the new set)
        $role->syncPermissions($request->input('permissions', []));

        return response()->json([
            'message' => 'Permissions updated successfully.',
            'role' => $role->load('permissions'),
        ]);
    }
}
