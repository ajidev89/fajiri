<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    /**
     * List all administrative users.
     */
    public function indexAdminUsers()
    {
        $userRole = Role::where('slug', 'user')->first();
        
        $admins = User::with(['role', 'profile', 'wallet'])
            ->where('role_id', '!=', $userRole?->id)
            ->latest()
            ->paginate(15);

        return $this->handleSuccessCollectionResponse(
            'Admin users fetched successfully',
            UserResource::collection($admins)
        );
    }

    /**
     * Store a new administrative user.
     */
    public function storeAdminUser(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:roles,id',
            'country_id' => 'required|exists:countries,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'nullable|string|in:male,female,other',
            'dob' => 'nullable|date',
        ]);

        $role = Role::findOrFail($validated['role_id']);
        if ($role->slug === 'user') {
            return $this->handleErrorResponse('Cannot create a regular user via admin user endpoints.', 400);
        }

        $country = \App\Models\Country::findOrFail($validated['country_id']);

        $user = User::create([
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
            'country_id' => $validated['country_id'],
            'account_type' => \App\Enums\User\AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'phone_verified_at' => ($validated['phone'] ?? null) ? now() : null,
            'status' => 'active',
        ]);

        $user->profile()->create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'gender' => $validated['gender'] ?? 'male',
            'dob' => $validated['dob'] ?? '1990-01-01',
            'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($validated['first_name'] . ' ' . $validated['last_name']),
        ]);

        $user->wallet()->create([
            'currency' => $country->currency ?? 'NGN',
            'balance' => 0,
        ]);

        $user->load(['role', 'profile', 'wallet']);

        return $this->handleSuccessResponse(
            'Admin user created successfully',
            new UserResource($user),
            201
        );
    }

    /**
     * Update an administrative user.
     */
    public function updateAdminUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
            'status' => 'nullable|string|in:active,suspended,deactivated',
        ]);

        if ($user->email === 'unite@fajiri.org' && $validated['role_id'] != $user->role_id) {
            return $this->handleErrorResponse('The primary Super Admin role cannot be changed.', 403);
        }

        $user->role_id = $validated['role_id'];
        if (isset($validated['status'])) {
            $user->status = $validated['status'];
        }
        $user->save();

        $user->load(['role', 'profile', 'wallet']);

        return $this->handleSuccessResponse(
            'Admin user updated successfully',
            new UserResource($user)
        );
    }

    /**
     * Delete/Deactivate an administrative user.
     */
    public function deleteAdminUser(User $user)
    {
        if ($user->email === 'unite@fajiri.org') {
            return $this->handleErrorResponse('The primary Super Admin user cannot be deleted.', 403);
        }

        $user->delete();

        return $this->handleSuccessResponse('Admin user deleted successfully');
    }
}
