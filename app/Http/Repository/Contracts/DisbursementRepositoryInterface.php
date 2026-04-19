<?php

namespace App\Http\Repository\Contracts;

interface DisbursementRepositoryInterface
{
    public function all();
    public function find($id);
    public function request(array $data);
    public function disburse($id, $proofFile);
    public function reject($id, $reason);
}
