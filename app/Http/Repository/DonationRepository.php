<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\DonationRepositoryInterface;
use App\Models\Donation;
use Illuminate\Support\Facades\DB;

class DonationRepository implements DonationRepositoryInterface
{
    /**
     * List donations ordered by highest base USD amount first (ranking).
     */
    public function index(?string $donatableType = null, $request = null)
    {
        $query = Donation::with(['donatable', 'user.profile'])
            ->when($donatableType, fn ($q) => $q->where('donatable_type', $donatableType));

        if ($request && $request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        } elseif (! $request || $request->input('status') !== 'all') {
            $query->where('status', 'completed');
        }

        if ($request && $request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('reference', 'like', "%{$term}%");
            });
        }

        $sortBy = $request && in_array($request->input('sort_by'), ['created_at', 'amount', 'base_amount_usd', 'name', 'status'], true)
            ? $request->input('sort_by')
            : 'base_amount_usd';
        $sortOrder = $request && in_array(strtolower((string) $request->input('sort_order', 'desc')), ['asc', 'desc'], true)
            ? strtolower((string) $request->input('sort_order', 'desc'))
            : 'desc';

        return $query->orderBy($sortBy, $sortOrder)
            ->orderBy('created_at', 'desc')
            ->paginate($request?->per_page ?? 15);
    }

    public function create(array $data)
    {
        return Donation::create($data);
    }

    public function findByDonatable(string $type, $id)
    {
        return Donation::where('donatable_type', $type)
            ->where('donatable_id', $id)
            ->where('status', 'completed')
            ->orderBy('base_amount_usd', 'desc')
            ->get();
    }

    public function findByReference(string $reference)
    {
        return Donation::where('reference', $reference)->first();
    }

    /**
     * Top donor leaderboard ranked by total cumulative base_amount_usd.
     */
    public function leaderboard(int $limit = 10, ?string $donatableType = null, $donatableId = null)
    {
        $query = Donation::select(
            'user_id',
            'email',
            'name',
            DB::raw('SUM(base_amount_usd) as total_donated_usd'),
            DB::raw('COUNT(id) as total_donations_count')
        )
        ->where('status', 'completed');

        if ($donatableType && $donatableId) {
            $query->where('donatable_type', $donatableType)
                  ->where('donatable_id', $donatableId);
        }

        return $query->groupBy('user_id', 'email', 'name')
            ->orderBy('total_donated_usd', 'desc')
            ->with(['user.profile', 'user.country'])
            ->take($limit)
            ->get();
    }
}
