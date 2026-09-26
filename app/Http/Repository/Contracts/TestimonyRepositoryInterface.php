<?php

namespace App\Http\Repository\Contracts;

interface TestimonyRepositoryInterface
{
    public function index($request);

    public function show(string $slug);

    public function store($request);

    public function update($request, string $id);

    public function destroy(string $id);
}
