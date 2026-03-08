<?php

namespace App\Http\Repository\Contracts;

interface UserRepositoryInterface {
    public function index();
    public function changePassword($request);
}