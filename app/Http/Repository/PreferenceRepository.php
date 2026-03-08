<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\PreferenceRepositoryInterface;
use App\Models\Preference;

class PreferenceRepository implements PreferenceRepositoryInterface
{
    public function getForUser($userId)
    {
        return Preference::firstOrCreate(['user_id' => $userId,
        'notification_sound' => true,
        'auto_update_software' => true,
        'community_updates' => true,
        'project_updates' => true,
        'event_updates' => true,
        'receive_payment_confirmation' => true,
        'membership_status_updates' => true,
    ]);
    }

    public function updateForUser($userId, array $data)
    {
        $preference = $this->getForUser($userId);
        $preference->update($data);
        return $preference;
    }
}
