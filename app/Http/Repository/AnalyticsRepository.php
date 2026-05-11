<?php

namespace App\Http\Repository;

use App\Models\Donation;
use App\Models\Campaign;
use App\Models\Need;
use App\Models\User;
use App\Models\Disbursement;
use App\Enums\Disbursement\Status;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use App\Http\Repository\Contracts\AnalyticsRepositoryInterface;

class AnalyticsRepository implements AnalyticsRepositoryInterface {

    public function __construct(
        protected Donation $donation, 
        protected Campaign $campaign, 
        protected Need $need, 
        protected User $user,
        protected \App\Services\CurrencyService $currencyService
    ){

    }

    public function index($request = null)
    {
        $added_by = $request ? $request->added_by : null;

        $donationQuery = $this->donation->query();
        $campaignQuery = $this->campaign->query();
        $needQuery = $this->need->query();

        if ($added_by) {
            $donationQuery->whereHasMorph('donatable', [\App\Models\Campaign::class, \App\Models\Need::class], function ($query) use ($added_by) {
                $query->where('added_by', $added_by);
            });
            $campaignQuery->where('added_by', $added_by);
            $needQuery->where('added_by', $added_by);
        }

        return [
            "total_donations" => $donationQuery->count(),
            "total_donations_amount" => $this->donatedCurrency($request),
            "active_campaigns" => $campaignQuery->where('status', 'active')->count(),
            "active_campaigns_percentage_change" => $this->calculatePercentageChange($this->campaign, ['status' => 'active'], $request),
            "active_needs" => $needQuery->count(),
            "active_needs_percentage_change" => $this->calculatePercentageChange($this->need, [], $request),
            "total_users" => $this->user->whereHas('role', function ($query) {
                $query->where('name', 'user');
            })->count(),
            "total_users_percentage_change" => $this->calculatePercentageChange(
                $this->user->whereHas('role', function ($query) {
                    $query->where('name', 'user');
                })
            ),
        ];
    }

    public function donatedCurrency($request = null){
        $added_by = $request ? $request->added_by : null;
        $query = $this->donation->select('currency', DB::raw('SUM(amount) as total_amount'))
            ->where('status', 'completed');

        if ($added_by) {
            $query->whereHasMorph('donatable', [\App\Models\Campaign::class, \App\Models\Need::class], function ($query) use ($added_by) {
                $query->where('added_by', $added_by);
            });
        }

        return $query->groupBy('currency')->get();
    }

    public function disbursementStats()
    {
        // 1. Calculate Total Donated in NGN
        $donationsByCurrency = $this->donatedCurrency();
        $totalDonatedInNgn = 0;
        foreach ($donationsByCurrency as $item) {
            $totalDonatedInNgn += $this->currencyService->convert($item->total_amount, $item->currency, 'NGN');
        }

        // 2. Calculate Total Disbursed in NGN (using the converted_amount we just added)
        $totalDisbursedInNgn = Disbursement::where('status', Status::COMPLETED)->sum('converted_amount');

        $availableFunds = $totalDonatedInNgn - $totalDisbursedInNgn;

        $stats = Disbursement::select('status', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'), 'currency')
            ->groupBy('status', 'currency')
            ->get();

        $formatedStats = [
            'pending' => ['count' => 0, 'amounts' => []],
            'completed' => ['count' => 0, 'amounts' => []],
            'rejected' => ['count' => 0, 'amounts' => []],
        ];

        foreach ($stats as $stat) {
            $status = $stat->status->value;
            $formatedStats[$status]['count'] += $stat->count;
            $formatedStats[$status]['amounts'][$stat->currency] = (float) $stat->total_amount;
        }

        return [
            'available_funds_ngn' => round($availableFunds, 2),
            'pending_disbursements' => $formatedStats['pending'],
            'approved_disbursements' => $formatedStats['completed'],
            'rejected_disbursements' => $formatedStats['rejected'],
        ];
    }

    private function calculatePercentageChange(Model|Builder $modelOrQuery, ?array $filter = [], $request = null): float|int
    {
        $added_by = $request ? $request->added_by : null;
        $baseQuery = $modelOrQuery instanceof Builder 
            ? clone $modelOrQuery 
            : $modelOrQuery->newQuery();

        if ($filter) {
            $baseQuery->where($filter);
        }

        if ($added_by) {
            // Check if the model has added_by or if it's Donation (needs morph check)
            $model = $modelOrQuery instanceof Builder ? $modelOrQuery->getModel() : $modelOrQuery;
            if ($model instanceof \App\Models\Donation) {
                $baseQuery->whereHasMorph('donatable', [\App\Models\Campaign::class, \App\Models\Need::class], function ($query) use ($added_by) {
                    $query->where('added_by', $added_by);
                });
            } else {
                $baseQuery->where('added_by', $added_by);
            }
        }

        $currentMonthQuery = (clone $baseQuery)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        $lastMonthQuery = (clone $baseQuery)->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]);

        $currentMonthCount = $currentMonthQuery->count();
        $lastMonthCount = $lastMonthQuery->count();

        if ($lastMonthCount === 0) {
            return $currentMonthCount > 0 ? 100 : 0;
        }

        return round((($currentMonthCount - $lastMonthCount) / $lastMonthCount) * 100, 2);
    }


    public function donationChartlyAnnualy($request = null)
    {
        $added_by = $request ? $request->added_by : null;

        $donationQuery = $this->donation
            ->select(
                DB::raw('MONTH(created_at) as month_num'),
                'currency',
                DB::raw('COUNT(id) as no_of_donations'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->where('status', 'completed')
            ->whereYear('created_at', now()->year);

        if ($added_by) {
            $donationQuery->whereHasMorph('donatable', [\App\Models\Campaign::class, \App\Models\Need::class], function ($query) use ($added_by) {
                $query->where('added_by', $added_by);
            });
        }

        $donations = $donationQuery->groupBy('month_num', 'currency')->get();

        $formatted = [];

        for ($i = 1; $i <= 12; $i++) {
            $monthName = \Carbon\Carbon::create()->month($i)->format('F');
            $monthDonations = $donations->where('month_num', $i);
            
            $amounts = [];
            $totalDonations = 0;
            
            foreach ($monthDonations as $donation) {
                $amounts[$donation->currency] = (float) $donation->total_amount;
                $totalDonations += $donation->no_of_donations;
            }

            $formatted[] = [
                'month' => $monthName,
                'no_of_donations' => $totalDonations,
                'amounts' => (object) $amounts,
            ];
        }

        return $formatted;
    }


    //piechart for top performing campaigns
    public function topPerformingCampaigns($request = null)
    {
        $added_by = $request ? $request->added_by : null;

        $query = $this->campaign->query();

        if ($added_by) {
            $query->where('added_by', $added_by);
        }

        return $query
            ->withSum(['donations as total_raised' => function ($query) {
                $query->where('status', 'completed');
            }], 'converted_amount')
            ->orderByDesc('total_raised')
            ->take(5)
            ->get()
            ->map(function ($campaign) {
                return [
                    'id' => $campaign->id,
                    'title' => $campaign->title,
                    'total_raised' => (float) $campaign->total_raised,
                ];
            });
    }

    public function leaderboard()
    {
        $limit = 50;
        
        return $this->user->whereHas('role', function ($query) {
                $query->where('slug', 'user');
            })
            ->with(['profile'])
            ->withCount([
                'referrals',
                'donations as campaign_donations_count' => function ($query) {
                    $query->where('donatable_type', \App\Models\Campaign::class)
                        ->where('status', 'completed');
                },
                'donations as need_donations_count' => function ($query) {
                    $query->where('donatable_type', \App\Models\Need::class)
                        ->where('status', 'completed');
                },
                'eventAttendees as event_attendance_count'
            ])
            ->get(['id', 'username', 'referrals_count', 'campaign_donations_count', 'need_donations_count', 'event_attendance_count'])
            ->map(function ($user) {
                $user->name = ($user->profile->first_name ?? '') . ' ' . ($user->profile->last_name ?? '');
                $user->total_engagement = $user->referrals_count + 
                                        $user->campaign_donations_count + 
                                        $user->need_donations_count + 
                                        $user->event_attendance_count;
                return $user;
            })
            ->sortByDesc('total_engagement')
            ->values()
            ->take($limit);
    }
}