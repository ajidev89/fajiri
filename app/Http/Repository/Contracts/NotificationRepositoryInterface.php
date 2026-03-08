<?php

namespace App\Http\Repository\Contracts;

interface NotificationRepositoryInterface
{
    public function index();
    public function create(array $data);
    public function destroy(string $id);
}
