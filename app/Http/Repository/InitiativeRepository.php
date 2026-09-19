<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\InitiativeRepositoryInterface;
use App\Models\Initiative;

class InitiativeRepository implements InitiativeRepositoryInterface 
{
    public function __construct(Initiative $model)
    {
        $this->model = $model;
    }

    public function index($request = null)
    {
        $query = $this->model->query();

        if ($request && $request->has('added_by')) {
            $query->where('added_by', $request->added_by);
        }

        if ($request && $request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request && $request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $sortBy = $request && in_array($request->input('sort_by'), ['created_at', 'updated_at', 'title', 'status'], true)
            ? $request->input('sort_by')
            : 'created_at';
        $sortOrder = $request && in_array(strtolower((string) $request->input('sort_order', 'desc')), ['asc', 'desc'], true)
            ? strtolower((string) $request->input('sort_order', 'desc'))
            : 'desc';

        return $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);
    }

    public function find($id)
    {
        return $this->model->findOrFail($id);
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update($id, array $data)
    {
        $initiative = $this->model->findOrFail($id);
        $initiative->update($data);
        return $initiative;
    }

    public function delete($id)
    {
        $initiative = $this->find($id);
        return $initiative->delete();
    }
}