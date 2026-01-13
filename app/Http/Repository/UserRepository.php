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
        info($this->user());
        return $this->handleSuccessResponse("Successfully fetched user", new UserResource($this->user()));
    }




}