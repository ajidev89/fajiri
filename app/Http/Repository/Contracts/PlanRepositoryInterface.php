<?php

namespace App\Http\Repository\Contracts;

interface PlanRepositoryInterface
{
    public function all(array $filters = []);
    public function findById($id);
    public function store(array $data);
    public function update($id, array $data);
    public function delete($id);
    public function subscribeUser($user, $planId, $duration = null, $autoRenew = true);
    public function initializeSubscription($user, $planId, array $options = []);
    public function renewSubscription($userPlanId);
}
