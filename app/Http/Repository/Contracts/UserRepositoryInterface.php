<?php

namespace App\Http\Repository\Contracts;

interface UserRepositoryInterface {
    public function index();
    public function changePassword($request);
    public function transactions($request);
    public function updateAvatar($request);
    public function updatePin($request);
}