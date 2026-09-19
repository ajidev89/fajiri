<?php

namespace App\Http\Repository\Contracts;

interface CategoryRepositoryInterface
{
    public function index($request = null);
    public function store($request);
    public function update($request, $id);
    public function destroy($id);
}
