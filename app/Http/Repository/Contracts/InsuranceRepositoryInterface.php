<?php

namespace App\Http\Repository\Contracts;

interface InsuranceRepositoryInterface
{
    public function index($request = null);
    public function all();
    public function find($id);
    public function create($data);
    public function update($id, $data);
    public function delete($id);
}