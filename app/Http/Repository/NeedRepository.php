<?php

namespace App\Http\Repository;

use App\Models\Need;


class NeedRepository {

    public function __construct(public Need $need)
    {
    }

    public function index()
    {
        return $this->need->latest()->paginate(10);
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
    
