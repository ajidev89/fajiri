<?php

namespace App\Http\Repository\Contracts;

interface UserRepositoryInterface {
    public function index();
    public function changePassword($request);
    public function transactions($request);
    public function transfer($request);
    public function updateAvatar($request);
    public function updatePin($request);
    public function updateProfile($request);
    public function withdrawAccount();
    public function referrals();
}