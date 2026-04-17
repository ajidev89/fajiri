<?php

namespace App\Http\Repository\Contracts;

interface PartnerRepositoryInterface
{
    public function index($request);
    public function show($slug);
    public function store($request);
    public function update($request, $id);
    public function destroy($id);
}
