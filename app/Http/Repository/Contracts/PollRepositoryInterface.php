<?php

namespace App\Http\Repository\Contracts;

interface PollRepositoryInterface
{
    public function index($request);
    public function show($poll);
    public function store($request);
    public function update($request, $poll);
    public function destroy($poll);
    public function summary($poll);
    public function responses($poll, $request);
    public function vote($request, $poll);
}
