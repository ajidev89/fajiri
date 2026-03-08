<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\PreferenceRepositoryInterface;
use App\Models\Preference;

class PreferenceRepository implements PreferenceRepositoryInterface
{
    public function getForUser($userId)
    {
        return Preference::firstOrCreate(['user_id' => $userId])->fresh();
    }

    public function updateForUser($userId, array $data)
    {
        $preference = $this->getForUser($userId);
        $preference->update($data);
        return $preference;
    }
}
