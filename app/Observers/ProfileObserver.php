<?php

namespace App\Observers;

use App\Models\Profile;

class ProfileObserver
{
    public function created(Profile $profile): void
    {
        $profile->loadMissing('user');
        $profile->user?->ensureSelfFamilyMember(true);
    }
}
