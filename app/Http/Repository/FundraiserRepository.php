<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\FundraiserRepositoryInterface;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Exception;

class FundraiserRepository implements FundraiserRepositoryInterface
{
    public function __construct(protected User $model, protected Role $role)
    {
    }

    public function index()
    {
        return $this->model->fundraisers()
            ->with(['role', 'campaigns', 'needs', 'profile'])
            ->latest()
            ->get();
    }

    public function store(array $data)
    {
        DB::beginTransaction();
        try {
            $role = $this->role->where('slug', 'fundraiser')->firstOrFail();

            $user = $this->model->create([
                'username' => $data['username'] ?? Str::before($data['email'], '@'),
                'email' => $data['email'],
                'password' => Hash::make($data['password'] ?? Str::random(12)),
                'role_id' => $role->id,
                'country_id' => $data['country_id'] ?? null,
                'status' => 'active',
            ]);

            $user->profile()->create([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
            ]);

            // Create wallet
            $user->wallet()->create([
                'currency' => $data['currency'] ?? 'NGN',
                'balance' => 0
            ]);

            DB::commit();
            return $user;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function sendResetLink(User $user)
    {
        // Generate password reset link
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw new Exception(__($status));
        }

        return true;
    }
}
