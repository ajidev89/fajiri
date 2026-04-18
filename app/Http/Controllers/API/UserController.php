<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\UserRepositoryInterface;
use App\Http\Requests\User\TransferRequest;  

class UserController extends Controller
{

    public function __construct(protected UserRepositoryInterface $userRepositoryInterface)
    {}

    public function index(){
        return $this->userRepositoryInterface->index();
    }

    public function changePassword(\App\Http\Requests\User\UpdatePasswordRequest $request){
        return $this->userRepositoryInterface->changePassword($request);
    }

    public function updateAvatar(\Illuminate\Http\Request $request){
        return $this->userRepositoryInterface->updateAvatar($request);
    }

    public function updatePin(\App\Http\Requests\User\CreatePinRequest $request){
        return $this->userRepositoryInterface->updatePin($request);
    }

    public function transactions(\Illuminate\Http\Request $request){
        return $this->userRepositoryInterface->transactions($request);
    }

    public function transfer(TransferRequest $request){
        return $this->userRepositoryInterface->transfer($request);
    }

    public function withdrawAccount(){
        return $this->userRepositoryInterface->withdrawAccount();
    }

    public function referrals(){
        return $this->userRepositoryInterface->referrals();
    }
}
