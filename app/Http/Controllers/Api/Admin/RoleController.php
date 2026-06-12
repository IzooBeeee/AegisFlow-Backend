<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Get all roles with their permissions
     */
    public function index()
    {
        $roles = Role::with('permissions')->get();
        return response()->json([
            'status' => 'success',
            'data' => $roles
        ]);
    }

    /**
     * Get all available permissions grouped by group_name
     */
    public function permissions()
    {
        $permissions = Permission::all()->groupBy('group_name');
        return response()->json([
            'status' => 'success',
            'data' => $permissions
        ]);
    }

    /**
     * Sync permissions for a role
     */
    public function syncPermissions(Request $request, $id)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name'
        ]);

        $role = Role::findOrFail($id);
        
        // Prevent modifying the city_admin role if you want to keep it strictly with all permissions
        // if ($role->name === 'city_admin') {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'Cannot modify super admin permissions.'
        //     ], 403);
        // }

        $role->syncPermissions($request->permissions);

        return response()->json([
            'status' => 'success',
            'message' => 'Permissions updated successfully',
            'data' => $role->load('permissions')
        ]);
    }
}
