<?php

namespace App\Http\Repository\Contracts;

use App\Models\Need;

interface NeedRepositoryInterface
{
    public function index();
    public function find(Need $need);
    public function create(array $data);
    public function update(Need $need, array $data);
    public function delete(Need $need);
}
