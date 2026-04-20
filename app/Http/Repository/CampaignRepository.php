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

    public function analytics()
    {
        return [
            'total_campaigns' => $this->campaign->count(),
            'total_percentage_change' => $this->calculatePercentageChange(),
            'active_campaigns' => $this->campaign->where('status', 'active')->count(),
            'active_percentage_change' => $this->calculatePercentageChange('active'),
            'pending_campaigns' => $this->campaign->where('status', 'pending')->count(),
            'pending_percentage_change' => $this->calculatePercentageChange('pending'),
            'completed_campaigns' => $this->campaign->where('status', 'completed')->count(),
            'completed_percentage_change' => $this->calculatePercentageChange('completed'),
            'rejected_campaigns' => $this->campaign->where('status', 'rejected')->count(),
            'rejected_percentage_change' => $this->calculatePercentageChange('rejected'),
        ];
    }

    private function calculatePercentageChange(?string $status = null): float|int
    {
        $currentMonthQuery = $this->campaign->newQuery()->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        $lastMonthQuery = $this->campaign->newQuery()->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]);

        if ($status) {
            $currentMonthQuery->where('status', $status);
            $lastMonthQuery->where('status', $status);
        }

        $currentMonthCount = $currentMonthQuery->count();
        $lastMonthCount = $lastMonthQuery->count();

        if ($lastMonthCount === 0) {
            return $currentMonthCount > 0 ? 100 : 0;
        }

        return round((($currentMonthCount - $lastMonthCount) / $lastMonthCount) * 100, 2);
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
            ->when($request->added_by, function ($query) use ($request) {
                $query->where('added_by', $request->added_by);
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
