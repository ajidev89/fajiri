<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\UserRepositoryInterface;
use App\Http\Resources\User\UserResource;
use App\Http\Services\CloudinaryService;
use App\Http\Traits\ResponseTrait;
use App\Http\Traits\AuthUserTrait;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserRepository implements UserRepositoryInterface {

    use ResponseTrait, AuthUserTrait;

    public function index() {
        return $this->handleSuccessResponse("Successfully fetched user", new UserResource($this->user()));
    }

    public function changePassword($request) {
        try {
            $user = $this->user();
            $user->update([
                'password' => \Illuminate\Support\Facades\Hash::make($request->password)
            ]);

            return $this->handleSuccessResponse("Password successfully updated");
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }

    public function updateAvatar($request) {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        try {
            $user = $this->user();
            $profile = $user->profile;

            if ($request->hasFile('avatar')) {
                // Delete old avatar if it exists
                if ($profile->avatar && \Illuminate\Support\Facades\Storage::disk('public')->exists($profile->avatar)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($profile->avatar);
                }

                $path = app(CloudinaryService::class)->uploadImage($request->file('avatar'));
                $profile->update(['avatar' => $path]);

                return $this->handleSuccessResponse("Avatar successfully updated", ['avatar_url' => $path]);
            }

            return $this->handleErrorResponse("Avatar file not found", 400);
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }

    public function updatePin($request) {
        try {
            $user = $this->user();
            if($user->pin) {
                if(!Hash::check($request->current_pin, $user->pin)) {
                    return $this->handleErrorResponse("Incorrect current pin", 400);
                }
            }
            $user->update([
                'pin' => Hash::make($request->pin)
            ]);

            return $this->handleSuccessResponse("Pin successfully updated");
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }
}