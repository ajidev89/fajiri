<?php

namespace App\Http\Repository\Contracts;


interface OtpRepositoryInterface {
    public function create($request);
    public function verify($request);
}