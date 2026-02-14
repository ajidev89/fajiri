<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\AuthRepositoryInterface;
use App\Http\Repository\Contracts\GoogleRepositoryInterface;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginGoogleRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Otp\VerifyRequest;

class AuthController extends Controller
{

    public function __construct(protected AuthRepositoryInterface $authRepositoryInterface, 
                                protected GoogleRepositoryInterface $googleRepositoryInterface)
    {}

    public function register(RegisterRequest $request){
        return $this->authRepositoryInterface->register($request);
    }

    public function login(LoginRequest $request){
        return $this->authRepositoryInterface->login($request);
    }

    public function loginWithGoogle(LoginGoogleRequest $request){
        return $this->authRepositoryInterface->loginWithGoogle($request);
    }

    public function generateGoogleUrl(){
        return $this->googleRepositoryInterface->generateGoogleUrl();
    }
    
    public function handleGoogleCallback(){
        return $this->googleRepositoryInterface->handleGoogleCallback();
    }

    public function changePassword(ChangePasswordRequest $request){
        return $this->authRepositoryInterface->changePassword($request);
    }

    public function generateToken(VerifyRequest $request){
        return $this->authRepositoryInterface->generateToken($request);
    }

    public function logout(){
        return $this->authRepositoryInterface->logout();
    }

}
