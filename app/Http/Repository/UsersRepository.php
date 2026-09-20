<?php

namespace App\Http\Repository;

use App\Enums\User\Status;
use App\Http\Repository\Contracts\UsersRepositoryInterface;
use App\Http\Traits\AppliesListQuery;
use App\Models\User;

class UsersRepository implements UsersRepositoryInterface
{
    use AppliesListQuery;

    public function __construct(public User $user) {}

    public function filteredQuery($request = null)
    {
        $search = $request?->input('search') ?? $request?->input('q');

        $query = $this->user->whereHas('role', fn ($q) => $q->where('slug', 'user'))
            ->with(['profile', 'role', 'country', 'wallet'])
            ->search($search)
            ->filter($request?->only(['status', 'account_type', 'sub_account_type', 'country_id']) ?? []);

        $this->applyListQuery(
            $query,
            $request,
            [],
            ['created_at', 'updated_at', 'email', 'status'],
        );

        return $query;
    }

    public function index($request = null)
    {
        return $this->filteredQuery($request)->paginate($request?->per_page ?? 10);
    }

    public function find(User $user)
    {
        return $user;
    }

    public function update(User $user, array $data)
    {
        $user->update($data);

        return $user;
    }

    public function suspend(User $user)
    {
        $user->update(['status' => 'suspended']);
        $user->tokens()->delete();
        $user->audit('status_change', 'User account has been suspended by an administrator.');

        return $user;
    }

    public function unsuspend(User $user)
    {
        $user->update(['status' => 'active']);
        $user->audit('status_change', 'User account has been unsuspended by an administrator.');

        return $user;
    }

    public function deactivate(User $user)
    {
        $user->update(['status' => Status::DEACTIVATED->value]);
        $user->tokens()->delete();
        $user->audit('status_change', 'User account has been deactivated by an administrator.');

        return $user;
    }

    public function reactivate(User $user)
    {
        $user->update(['status' => Status::ACTIVE->value]);
        $user->audit('status_change', 'User account has been reactivated by an administrator.');

        return $user;
    }

    public function delete(User $user)
    {
        $user->delete();

        return $user;
    }

    public function audits(User $user)
    {
        return $user->audits()->with('performer')->latest()->paginate(10);
    }

    public function donations(User $user)
    {
        return $user->donations()->with(['donatable', 'user.profile'])->latest()->paginate(10);
    }

    public function transactions(User $user)
    {
        return $user->transactions()->with('wallet')->latest()->paginate(10);
    }

    public function referrals(User $user)
    {
        return $user->referrals()->with(['profile', 'role'])->latest()->paginate(10);
    }

    public function updateNotificationToken(User $user, string $token)
    {
        $user->update(['notification_token' => $token]);

        return $user;
    }
}
