<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\AuthRepositoryInterface;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;

class AuthController extends Controller
{

    public function __construct(protected AuthRepositoryInterface $authRepositoryInterface)
    {}

    public function register(RegisterRequest $request){
        return $this->authRepositoryInterface->register($request);
    }

    public function login(LoginRequest $request){
        return $this->authRepositoryInterface->login($request);
    }

    public function changePassword(ChangePasswordRequest $request){
        return $this->authRepositoryInterface->changePassword($request);
    }

    public function logout(){
        return $this->authRepositoryInterface->logout();
    }

}
