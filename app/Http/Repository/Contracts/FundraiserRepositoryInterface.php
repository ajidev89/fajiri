<?php

namespace App\Http\Repository\Contracts;

use App\Models\User;

interface FundraiserRepositoryInterface
{
    public function index($request = null);
    public function filteredQuery($request = null);
    public function store(array $data);
    public function sendResetLink(User $user);
}
