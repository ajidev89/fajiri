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

    public function findByDonatable(string $type, $id)
    {
        return Donation::where('donatable_type', $type)->where('donatable_id', $id)->get();
    }

    public function findByReference(string $reference)
    {
        return Donation::where('reference', $reference)->first();
    }
}
