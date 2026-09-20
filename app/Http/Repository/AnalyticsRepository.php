<?php

namespace App\Http\Repository;

use App\Enums\Disbursement\Status;
use App\Http\Repository\Contracts\AnalyticsRepositoryInterface;
use App\Models\Campaign;
use App\Models\Disbursement;
use App\Models\Donation;
use App\Models\Need;
use App\Models\User;
use App\Services\CurrencyService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AnalyticsRepository implements AnalyticsRepositoryInterface
{
    public function __construct(
        protected Donation $donation,
        protected Campaign $campaign,
        protected Need $need,
        protected User $user,
        protected CurrencyService $currencyService
    ) {}

    public function index($request = null)
    {
        $added_by = $request ? $request->added_by : null;

        $donationQuery = $this->donation->query();
        $campaignQuery = $this->campaign->query();
        $needQuery = $this->need->query();

        if ($added_by) {
            $donationQuery->whereHasMorph('donatable', [Campaign::class, Need::class], function ($query) use ($added_by) {
                $query->where('added_by', $added_by);
            });
            $campaignQuery->where('added_by', $added_by);
            $needQuery->where('added_by', $added_by);
        }

        return [
            'total_donations' => $donationQuery->count(),
            'total_donations_amount' => [
                (object) ['currency' => '$', 'total_amount' => $this->sumDonatedUsd($request)],
            ],
            'active_campaigns' => $campaignQuery->where('status', 'active')->count(),
            'active_campaigns_percentage_change' => $this->calculatePercentageChange($this->campaign, ['status' => 'active'], $request),
            'active_needs' => $needQuery->count(),
            'active_needs_percentage_change' => $this->calculatePercentageChange($this->need, [], $request),
            'total_users' => $this->user->whereHas('role', function ($query) {
                $query->where('name', 'user');
            })->count(),
            'total_users_percentage_change' => $this->calculatePercentageChange(
                $this->user->whereHas('role', function ($query) {
                    $query->where('name', 'user');
                })
            ),
        ];
    }

    public function donatedCurrency($request = null)
    {
        $added_by = $request ? $request->added_by : null;
        $query = $this->donation->select('currency', DB::raw('SUM(amount) as total_amount'))
            ->where('status', 'completed');

        if ($added_by) {
            $query->whereHasMorph('donatable', [Campaign::class, Need::class], function ($query) use ($added_by) {
                $query->where('added_by', $added_by);
            });
        }

        return $query->groupBy('currency')->get();
    }

    public function disbursementStats()
    {
        $totalDonatedUsd = $this->sumDonatedUsd();
        $totalDisbursedUsd = $this->sumDisbursementUsd([Status::COMPLETED->value]);
        $availableFundsUsd = max(0.0, round($totalDonatedUsd - $totalDisbursedUsd, 2));

        $stats = Disbursement::select('status', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'), 'currency')
            ->groupBy('status', 'currency')
            ->get();

        $formatedStats = [
            'pending' => ['count' => 0, 'amounts' => ['USD' => 0]],
            'completed' => ['count' => 0, 'amounts' => ['USD' => 0]],
            'rejected' => ['count' => 0, 'amounts' => ['USD' => 0]],
        ];

        foreach ($stats as $stat) {
            $status = $stat->status instanceof Status ? $stat->status->value : $stat->status;

            if (! isset($formatedStats[$status])) {
                continue;
            }

            $formatedStats[$status]['count'] += $stat->count;
            $formatedStats[$status]['amounts']['USD'] += $this->usdFromAggregate($stat);
        }

        return [
            'available_funds_usd' => $availableFundsUsd,
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
            if ($model instanceof Donation) {
                $baseQuery->whereHasMorph('donatable', [Campaign::class, Need::class], function ($query) use ($added_by) {
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
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('SUM(base_amount_usd) as total_usd')
            )
            ->where('status', 'completed')
            ->whereYear('created_at', now()->year);

        if ($added_by) {
            $donationQuery->whereHasMorph('donatable', [Campaign::class, Need::class], function ($query) use ($added_by) {
                $query->where('added_by', $added_by);
            });
        }

        $donations = $donationQuery->groupBy('month_num', 'currency')->get();

        $formatted = [];

        for ($i = 1; $i <= 12; $i++) {
            $monthName = Carbon::create()->month($i)->format('F');
            $monthDonations = $donations->where('month_num', $i);

            $totalUsdAmount = 0;
            $totalDonations = 0;

            foreach ($monthDonations as $donation) {
                $totalUsdAmount += $this->usdFromAggregate($donation);
                $totalDonations += $donation->no_of_donations;
            }

            $formatted[] = [
                'month' => $monthName,
                'no_of_donations' => $totalDonations,
                'amounts' => (object) ['USD' => round($totalUsdAmount, 2)],
            ];
        }

        return $formatted;
    }

    private function sumDisbursementUsd(array $statuses): float
    {
        $rows = Disbursement::query()
            ->select('currency', DB::raw('SUM(amount) as total_amount'))
            ->whereIn('status', $statuses)
            ->groupBy('currency')
            ->get();

        $total = 0.0;
        foreach ($rows as $row) {
            $total += $this->usdFromAggregate($row);
        }

        return round($total, 2);
    }

    private function sumDonatedUsd($request = null): float
    {
        $query = $this->donation->query()->where('status', 'completed');
        $added_by = $request ? $request->added_by : null;

        if ($added_by) {
            $query->whereHasMorph('donatable', [Campaign::class, Need::class], function ($q) use ($added_by) {
                $q->where('added_by', $added_by);
            });
        }

        $usdSum = (float) (clone $query)->sum('base_amount_usd');
        if ($usdSum > 0) {
            return round($usdSum, 2);
        }

        $total = 0;
        foreach ($this->donatedCurrency($request) as $item) {
            $total += $this->usdFromAggregate($item);
        }

        return round($total, 2);
    }

    private function usdFromAggregate(object $item): float
    {
        $storedUsd = (float) ($item->total_usd ?? $item->base_amount_usd ?? 0);
        if ($storedUsd > 0) {
            return $storedUsd;
        }

        $amount = (float) ($item->total_amount ?? $item->amount ?? 0);
        $currency = strtoupper((string) ($item->currency ?? 'USD'));

        if (in_array($currency, ['USD', '$', ''], true)) {
            return $amount;
        }

        return $this->currencyService->convert($amount, $currency, 'USD');
    }

    // piechart for top performing campaigns
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

    public function leaderboard($request = null)
    {
        $query = $this->user->whereHas('role', function ($query) {
            $query->where('slug', 'user');
        });

        if ($request && $request->filled('country_id') && $request->country_id !== 'all') {
            $query->where('country_id', $request->country_id);
        }

        if ($request && $request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('username', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhereHas('profile', function ($profile) use ($term) {
                        $profile->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%");
                    });
            });
        }

        $users = $query->with(['profile', 'country'])
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
                'eventAttendees as event_attendance_count',
            ])
            ->get()
            ->map(function ($user) {
                $user->name = ($user->profile->first_name ?? '').' '.($user->profile->last_name ?? '');
                $user->country_iso2 = $user->country->iso2 ?? null;
                $user->total_engagement = $user->referrals_count +
                                        $user->campaign_donations_count +
                                        $user->need_donations_count +
                                        $user->event_attendance_count;

                return $user;
            });

        $sortable = [
            'total_engagement',
            'referrals_count',
            'campaign_donations_count',
            'need_donations_count',
            'event_attendance_count',
            'username',
            'created_at',
        ];
        $sortBy = $request && in_array($request->input('sort_by'), $sortable, true)
            ? $request->input('sort_by')
            : 'total_engagement';
        $sortOrder = $request && in_array(strtolower((string) $request->input('sort_order', 'desc')), ['asc', 'desc'], true)
            ? strtolower((string) $request->input('sort_order', 'desc'))
            : 'desc';

        $sorted = ($sortOrder === 'asc' ? $users->sortBy($sortBy) : $users->sortByDesc($sortBy))->values();

        $page = max((int) data_get($request, 'page', 1), 1);
        $perPage = min(max((int) data_get($request, 'per_page', 15), 1), 50);

        return new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            [
                'path' => $request instanceof Request ? $request->url() : '/',
                'query' => $request instanceof Request ? $request->query() : [],
            ]
        );
    }
}
