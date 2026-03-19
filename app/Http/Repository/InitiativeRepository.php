<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\InitiativeRepositoryInterface;
use App\Models\Initiative;

class InitiativeRepository implements InitiativeRepositoryInterface 
{
    public function index()
    {
        return Initiative::where('status', 'active')->latest()->paginate(10);
    }

    public function find($id)
    {
        return Initiative::findOrFail($id);
    }

    public function create(array $data)
    {
        return Initiative::create($data);
    }

    public function update($id, array $data)
    {
        $initiative = Initiative::findOrFail($id);
        $initiative->update($data);
        return $initiative;
    }

    public function delete($id)
    {
        $initiative = $this->find($id);
        return $initiative->delete();
    }
}