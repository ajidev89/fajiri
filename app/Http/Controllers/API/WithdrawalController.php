<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\WithdrawalRepositoryInterface;
use App\Http\Requests\Withdrawal\CreateRequest;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function __construct(private WithdrawalRepositoryInterface $withdrawalRepository) {}

    public function index() {
        return $this->withdrawalRepository->index();
    }

    public function store(CreateRequest $request) {
        return $this->withdrawalRepository->store($request);
    }

    public function destroy(Request $request) {
        return $this->withdrawalRepository->destroy($request);
    }

    public function banks() {
        return $this->withdrawalRepository->banks();
    }

    public function resolveBankAccount(Request $request) {
        return $this->withdrawalRepository->resolveBankAccount($request);
    }

    public function withdraw(Request $request) {
        return $this->withdrawalRepository->withdraw($request);
    }
}
