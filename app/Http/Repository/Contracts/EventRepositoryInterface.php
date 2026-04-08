<?php

namespace App\Http\Repository\Contracts;

interface EventRepositoryInterface
{
    public function index($request);
    public function show($slug);
    public function store($request);
    public function update($request, $id);
    public function destroy($id);
    public function attend($event);
    public function attendExternal($request, $event);
    public function initializePaystack($request, $event_id);
    public function verifyPaystack($reference);
}
