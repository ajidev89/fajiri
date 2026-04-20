<?php

namespace App\Http\Repository;

use App\Models\Need;

use App\Http\Repository\Contracts\NeedRepositoryInterface;

class NeedRepository implements NeedRepositoryInterface {

    public function __construct(public Need $need)
    {
    }

    public function index($request = null)
    {
        return $this->need->query()
            ->when($request && $request->added_by, function ($query) use ($request) {
                $query->where('added_by', $request->added_by);
            })
            ->latest()
            ->paginate(10);
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
    
