<?php

namespace App\Http\Repository\Contracts;

interface PreferenceRepositoryInterface
{
    public function getForUser($userId);
    public function updateForUser($userId, array $data);
}
