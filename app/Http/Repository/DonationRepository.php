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
    public function index()
    {
        return Donation::where('status', 'completed')
            ->orderBy('base_amount_usd', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);
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
