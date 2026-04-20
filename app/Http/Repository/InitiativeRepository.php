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

        if ($request && $request->has('status')) {
            $query->where('status', $request->status);
        }

        return $query->latest()->paginate(10);
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