<?php

namespace App\Http\Repository;

use App\Enums\Campagin\CampaignType;
use App\Http\Repository\Contracts\CampaignRepositoryInterface;
use App\Models\Campaign;

class CampaignRepository implements CampaignRepositoryInterface
{
    public function all($request)
    {
        return Campaign::query()
            ->when($request->campaign_type, function ($query) use ($request) {
                $query->where('campaign_type', $request->campaign_type);
            })
            ->when($request->type, function ($query) use ($request) {
                $query->where('type', $request->type);
            })
            ->where('status', 'active')
            ->latest()
            ->paginate(10);
    }

    public function urgentCampaigns()
    {
        $now = now();
        $tenDaysFromNow = now()->addDays(10);

        return Campaign::where('status', 'active')
            ->whereBetween('end_date', [$now, $tenDaysFromNow])
            ->latest()
            ->paginate(10);
    }

    public function find($id)
    {
        return Campaign::findOrFail($id);
    }

    public function create(array $data)
    {
        return Campaign::create($data);
    }

    public function update($id, array $data)
    {
        $campaign = Campaign::findOrFail($id);
        $campaign->update($data);
        return $campaign;
    }

    public function delete($id)
    {
        $campaign = $this->find($id);
        return $campaign->delete();
    }
}
