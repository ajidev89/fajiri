<?php

namespace App\Http\Repository;

use App\Enums\Campagin\CampaignType;
use App\Enums\Campagin\Type;
use App\Http\Repository\Contracts\CampaignRepositoryInterface;
use App\Models\Campaign;

class CampaignRepository implements CampaignRepositoryInterface
{
    public function __construct(public Campaign $campaign)
    {
    }

    public function all($request)
    {
        return $this->campaign->query()
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

        return $this->campaign->where('status', 'active')
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

    public function types()
    {
        return Type::toArray();
    }
}
