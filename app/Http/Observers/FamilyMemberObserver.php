<?php

namespace App\Http\Observers;

use App\Http\Services\CloudinaryService;
use App\Models\FamilyMember;

class FamilyMemberObserver
{
    /**
     * Handle the FamilyMember "creating" event.
     */
    public function creating(FamilyMember $familyMember): void
    {
        if (request()->hasFile('photo')) {
            $upload = app(CloudinaryService::class)->uploadImage(request()->file('photo'));
            $familyMember->photo = $upload['url'];
        }
    }

    /**
     * Handle the FamilyMember "updating" event.
     */
    public function updating(FamilyMember $familyMember): void
    {
        if (request()->hasFile('photo') && request()->file('photo')->isValid()) {
            $upload = app(CloudinaryService::class)->uploadImage(request()->file('photo'));
            $familyMember->photo = $upload['url'];
        }
    }
}
