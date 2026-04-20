<?php

namespace App\Http\Repository\Contracts;

interface MediaRepositoryInterface
{
    public function index();
    public function store(array $data, $file);
    public function delete($id);
}
