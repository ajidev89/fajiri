<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\UsersRepositoryInterface;
use App\Models\User;
use App\Enums\User\Status;

class UsersRepository implements UsersRepositoryInterface { 

    public function __construct(public User $user) {}

    public function index() {
        $users = $this->user->with('profile')->latest()->paginate(10);
        return $users;
    }

    public function find(User $user) {
        return $user;
    }


    public function update(User $user, array $data) {
        $user->update($data);
        return $user;
    }

    public function suspend(User $user) {
        $user->update(['status' => 'suspended']);
        $user->audit('status_change', 'User account has been suspended by an administrator.');
        return $user;
    }

    public function unsuspend(User $user) {
        $user->update(['status' => 'active']);
        $user->audit('status_change', 'User account has been unsuspended by an administrator.');
        return $user;
    }

    public function deactivate(User $user) {
        $user->update(['status' => Status::DEACTIVATED->value]);
        $user->audit('status_change', 'User account has been deactivated by an administrator.');
        return $user;
    }

    public function reactivate(User $user) {
        $user->update(['status' => Status::ACTIVE->value]);
        $user->audit('status_change', 'User account has been reactivated by an administrator.');
        return $user;
    }

    public function delete(User $user) {
        $user->delete();
        return $user;
    }

    public function audits(User $user) {
        return $user->audits()->with('performer')->latest()->paginate(10);
    }
    
    public function transactions(User $user) {
        return $user->transactions()->latest()->paginate(10);
    }
}