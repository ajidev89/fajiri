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

    public function analytics($request = null)
    {
        $added_by = $request ? $request->added_by : null;

        return [
            'total_campaigns' => $this->campaign->when($added_by, fn($q) => $q->where('added_by', $added_by))->count(),
            'total_percentage_change' => $this->calculatePercentageChange(null, $request),
            'active_campaigns' => $this->campaign->where('status', 'active')->when($added_by, fn($q) => $q->where('added_by', $added_by))->count(),
            'active_percentage_change' => $this->calculatePercentageChange('active', $request),
            'pending_campaigns' => $this->campaign->where('status', 'pending')->when($added_by, fn($q) => $q->where('added_by', $added_by))->count(),
            'pending_percentage_change' => $this->calculatePercentageChange('pending', $request),
            'completed_campaigns' => $this->campaign->where('status', 'completed')->when($added_by, fn($q) => $q->where('added_by', $added_by))->count(),
            'completed_percentage_change' => $this->calculatePercentageChange('completed', $request),
            'rejected_campaigns' => $this->campaign->where('status', 'rejected')->when($added_by, fn($q) => $q->where('added_by', $added_by))->count(),
            'rejected_percentage_change' => $this->calculatePercentageChange('rejected', $request),
        ];
    }

    private function calculatePercentageChange(?string $status = null, $request = null): float|int
    {
        $added_by = $request ? $request->added_by : null;

        $currentMonthQuery = $this->campaign->newQuery()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->when($added_by, fn($q) => $q->where('added_by', $added_by));
            
        $lastMonthQuery = $this->campaign->newQuery()
            ->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
            ->when($added_by, fn($q) => $q->where('added_by', $added_by));

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

    public function donatedCampaigns($request)
    {
        $userId = $request->user_id ?? auth()->id();

        return $this->campaign->query()
            ->whereHas('donations', function ($query) use ($userId) {
                $query->where('user_id', $userId)->where('status', 'completed');
            })
            ->latest()
            ->paginate(10);
    }
}
