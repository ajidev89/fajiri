<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\DonationRepositoryInterface;
use App\Models\Donation;

class DonationRepository implements DonationRepositoryInterface
{
    public function create(array $data)
    {
        return Donation::create($data);
    }

    public function findByCampaign($campaignId)
    {
        return Donation::where('campaign_id', $campaignId)->get();
    }

    public function findByReference(string $reference)
    {
        return Donation::where('reference', $reference)->first();
    }
}
