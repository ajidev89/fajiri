<?php

namespace App\Http\Repository\Contracts;

use App\Models\User;

interface UsersRepositoryInterface
{
    public function index();

    public function find(User $user);

    public function update(User $user, array $data);

    public function suspend(User $user);

    public function unsuspend(User $user);

    public function delete(User $user);
    public function audits(User $user);
    public function transactions(User $user);
}