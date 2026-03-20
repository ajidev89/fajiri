<?php

namespace App\Http\Repository;

use App\Http\Traits\AuthUserTrait;
use App\Models\Insurance;
use App\Http\Repository\Contracts\InsuranceRepositoryInterface;

class InsuranceRepository implements InsuranceRepositoryInterface
{
    use AuthUserTrait;
    
    public function index()
    {
        return Insurance::where('country_id', $this->user()->country_id)->paginate();
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