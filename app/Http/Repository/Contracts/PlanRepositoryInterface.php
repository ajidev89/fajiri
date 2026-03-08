<?php

namespace App\Http\Repository\Contracts;

interface PlanRepositoryInterface
{
    public function all();
    public function findById($id);
    public function subscribeUser($user, $planId, $duration = null, $autoRenew = true);
    public function renewSubscription($userPlanId);
}
