<?php

namespace App\Http\Repository\Contracts;

interface InitiativeRepositoryInterface
{
    public function index($request = null);
    public function find($id);
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
}