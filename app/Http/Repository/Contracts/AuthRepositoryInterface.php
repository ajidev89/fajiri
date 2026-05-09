<?php

namespace App\Http\Repository\Contracts;


interface AuthRepositoryInterface {
    public function register($request);
    public function login($request);
    public function changePassword($request);
    public function loginWithGoogle($request);
    public function logout();
    public function generateToken($request);
    public function generateMagicLink($request);
    public function loginViaMagicLink($request);
}