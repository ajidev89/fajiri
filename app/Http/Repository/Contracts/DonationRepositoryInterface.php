<?php

namespace App\Http\Repository\Contracts;

interface DonationRepositoryInterface
{
    public function create(array $data);
    public function findByCampaign($campaignId);
}
