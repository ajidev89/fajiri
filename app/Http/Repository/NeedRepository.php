<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\NeedRepositoryInterface;
use App\Models\Need;

class NeedRepository implements NeedRepositoryInterface
{
    public function __construct(public Need $need) {}

    public function index($request = null)
    {
        $query = $this->need->query()
            ->withSum(['donations as donations_sum_converted_amount' => function ($query) {
                $query->where('status', 'completed');
            }], 'converted_amount')
            ->when($request && $request->added_by, function ($query) use ($request) {
                $query->where('added_by', $request->added_by);
            })
            ->when($request && $request->filled('status') && $request->status !== 'all', function ($query) use ($request) {
                $query->where('status', $request->status);
            });

        if ($request && $request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('location', 'like', "%{$term}%");
            });
        }

        $sortBy = $request && in_array($request->input('sort_by'), ['created_at', 'updated_at', 'name', 'status', 'amount'], true)
            ? $request->input('sort_by')
            : 'created_at';
        $sortOrder = $request && in_array(strtolower((string) $request->input('sort_order', 'desc')), ['asc', 'desc'], true)
            ? strtolower((string) $request->input('sort_order', 'desc'))
            : 'desc';

        return $query->orderBy($sortBy, $sortOrder)
            ->paginate($request?->per_page ?? 10);
    }

    public function find(Need $need)
    {
        return $need;
    }

    public function create(array $data)
    {
        return $this->need->create($data);
    }

    public function update(Need $need, array $data)
    {
        $need->update($data);

        return $need;
    }

    public function delete(Need $need)
    {
        return $need->delete();
    }
}
