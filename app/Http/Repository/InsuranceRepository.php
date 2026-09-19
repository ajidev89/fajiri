<?php

namespace App\Http\Repository;

use App\Http\Traits\AuthUserTrait;
use App\Models\Insurance;
use App\Http\Repository\Contracts\InsuranceRepositoryInterface;

class InsuranceRepository implements InsuranceRepositoryInterface
{
    use AuthUserTrait;
    
    public function index($request = null)
    {
        $query = Insurance::query()->with('country');

        if ($request && $request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        if ($request && $request->has('all')) {
            // keep all rows
        } elseif ($this->user()) {
            $query->where('country_id', $this->user()->country_id);
        }

        $sortBy = $request && in_array($request->input('sort_by'), ['created_at', 'updated_at', 'name'], true)
            ? $request->input('sort_by')
            : 'created_at';
        $sortOrder = $request && in_array(strtolower((string) $request->input('sort_order', 'desc')), ['asc', 'desc'], true)
            ? strtolower((string) $request->input('sort_order', 'desc'))
            : 'desc';

        return $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);
    }

    public function all()
    {
        return Insurance::paginate();
    }

    public function find($id)
    {
        return Insurance::find($id);
    }

    public function create($data)
    {
        return Insurance::create($data);
    }

    public function update($id, $data)
    {
        $insurance = Insurance::find($id);
        $insurance->update($data);
        return $insurance;
    }

    public function delete($id)
    {
        $insurance = Insurance::find($id);
        $insurance->delete();
        return $insurance;
    }
}