<?php

namespace App\Http\Repository\Contracts;

use App\Models\User;

interface UsersRepositoryInterface
{
    public function index();

    public function find(User $user);

    public function update(User $user, array $data);

    public function delete(User $user);
}