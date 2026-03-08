<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\UserRepositoryInterface;
use App\Http\Resources\User\UserResource;
use App\Http\Traits\ResponseTrait;
use App\Http\Traits\AuthUserTrait;
use App\Models\User;

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
}