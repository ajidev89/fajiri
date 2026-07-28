<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PermissionResource;
use App\Http\Resources\Admin\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RolePermissionController extends Controller
{
    protected array $protectedRoles = ['super-admin', 'admin', 'user', 'fundraiser'];

    /**
     * List all roles.
     */
    public function indexRoles()
    {
        $roles = Role::with('permissions')->get();
        return $this->handleSuccessResponse(
            'Roles fetched successfully',
            RoleResource::collection($roles)
        );
    }

    /**
     * Store a new role.
     */
    public function storeRole(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
        ]);

        if (!empty($validated['permissions'])) {
            $permissionIds = Permission::whereIn('name', $validated['permissions'])->pluck('id');
            $role->permissions()->sync($permissionIds);
        }

        $role->load('permissions');

        return $this->handleSuccessResponse(
            'Role created successfully',
            new RoleResource($role),
            201
        );
    }

    /**
     * Show a role.
     */
    public function showRole(Role $role)
    {
        $role->load('permissions');
        return $this->handleSuccessResponse(
            'Role fetched successfully',
            new RoleResource($role)
        );
    }

    /**
     * Update an existing role.
     */
    public function updateRole(Request $request, Role $role)
    {
        if (in_array($role->slug, $this->protectedRoles)) {
            $validated = $request->validate([
                'permissions' => 'nullable|array',
                'permissions.*' => 'string|exists:permissions,name',
            ]);
        } else {
            $validated = $request->validate([
                'name' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('roles', 'name')->ignore($role->id),
                ],
                'permissions' => 'nullable|array',
                'permissions.*' => 'string|exists:permissions,name',
            ]);
        }

        if (isset($validated['name']) && !in_array($role->slug, $this->protectedRoles)) {
            $role->name = $validated['name'];
            $role->save();
        }

        if (isset($validated['permissions'])) {
            if ($role->slug === 'super-admin') {
                return $this->handleErrorResponse('Super Admin permissions cannot be modified.', 403);
            }
            $permissionIds = Permission::whereIn('name', $validated['permissions'])->pluck('id');
            $role->permissions()->sync($permissionIds);
        }

        $role->load('permissions');

        return $this->handleSuccessResponse(
            'Role updated successfully',
            new RoleResource($role)
        );
    }

    /**
     * Delete a role.
     */
    public function deleteRole(Role $role)
    {
        if (in_array($role->slug, $this->protectedRoles)) {
            return $this->handleErrorResponse('Protected system roles cannot be deleted.', 403);
        }

        // Dissociate users before deletion
        \App\Models\User::where('role_id', $role->id)->update([
            'role_id' => Role::where('slug', 'user')->first()?->id
        ]);

        $role->permissions()->detach();
        $role->delete();

        return $this->handleSuccessResponse('Role deleted successfully');
    }

    /**
     * List all permissions.
     */
    public function indexPermissions()
    {
        $permissions = Permission::all();
        return $this->handleSuccessResponse(
            'Permissions fetched successfully',
            PermissionResource::collection($permissions)
        );
    }
}
