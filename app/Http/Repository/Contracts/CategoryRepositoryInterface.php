<?php

namespace App\Http\Repository\Contracts;

interface CategoryRepositoryInterface
{
    public function index();
    public function store($request);
    public function update($request, $id);
    public function destroy($id);
}
