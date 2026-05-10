<?php

namespace App\Http\Repository\Contracts;

interface PaymentRepositoryInterface
{
    public function initialize($user, array $data);
    public function verify(string $reference);
}
