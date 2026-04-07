<?php

namespace App\Http\Repository\Contracts;

interface WithdrawalRepositoryInterface {
    public function index();
    public function store($request);
    public function destroy($request);
    public function banks();
    public function resolveBankAccount($request);
    public function withdraw($request);
}