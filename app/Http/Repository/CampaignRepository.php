<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\CampaignRepositoryInterface;
use App\Models\Campaign;

class CampaignRepository implements CampaignRepositoryInterface
{
    public function all()
    {
        return Campaign::where('status', 'active')->latest()->paginate(10);
    }

    public function find($id)
    {
        return Campaign::where('status', 'active')->findOrFail($id);
    }

    public function create(array $data)
    {
        return Campaign::create($data);
    }

    public function update($id, array $data)
    {
        $campaign = $this->find($id);
        $campaign->update($data);
        return $campaign;
    }

    public function delete($id)
    {
        $campaign = $this->find($id);
        return $campaign->delete();
    }
}
