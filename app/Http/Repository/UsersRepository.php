<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\UsersRepositoryInterface;
use App\Models\User;

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

    public function delete(User $user) {
        $user->delete();
        return $user;
    }
}