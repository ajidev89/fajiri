<?php

namespace App\Http\Repository;

use App\Models\Donation;
use App\Models\Campaign;
use App\Models\Need;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use App\Http\Repository\Contracts\AnalyticsRepositoryInterface;

class AnalyticsRepository implements AnalyticsRepositoryInterface {

    public function __construct(protected Donation $donation, protected Campaign $campaign, protected Need $need, protected User $user){

    }

    public function index()
    {
        return [
            "total_donations" => $this->donation->count(),
            "total_donations_amount" => $this->donatedCurrency(),
            "active_campaigns" => $this->campaign->where('status', 'active')->count(),
            "active_campaigns_percentage_change" => $this->calculatePercentageChange($this->campaign, ['status' => 'active']),
            "active_needs" => $this->need->count(),
            "active_needs_percentage_change" => $this->calculatePercentageChange($this->need),
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

    public function donatedCurrency(){
        return $this->donation->select('currency', DB::raw('SUM(amount) as total_amount'))->where('status', 'completed')->groupBy('currency')->get();
    }

    private function calculatePercentageChange(Model|Builder $modelOrQuery, ?array $filter = [] ): float|int
    {
        $baseQuery = $modelOrQuery instanceof Builder 
            ? clone $modelOrQuery 
            : $modelOrQuery->newQuery();

        if ($filter) {
            $baseQuery->where($filter);
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


    public function donationChartlyAnnualy()
    {
        $donations = $this->donation
            ->select(
                DB::raw('MONTH(created_at) as month_num'),
                'currency',
                DB::raw('COUNT(id) as no_of_donations'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->where('status', 'completed')
            ->whereYear('created_at', now()->year)
            ->groupBy('month_num', 'currency')
            ->get();

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
    public function topPerformingCampaigns()
    {
        return $this->campaign
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
                $query->where('name', 'user');
            })
            ->withCount([
                'referrals',
                'donations as campaign_donations_count' => function ($query) {
                    $query->where('donatable_type', Campaign::class)
                        ->where('status', 'completed');
                },
                'donations as need_donations_count' => function ($query) {
                    $query->where('donatable_type', Need::class)
                        ->where('status', 'completed');
                },
                'eventAttendees as event_attendance_count'
            ])
            ->get(['id', 'username', 'referrals_count', 'campaign_donations_count', 'need_donations_count', 'event_attendance_count'])
            ->map(function ($user) {
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