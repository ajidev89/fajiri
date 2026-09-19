<?php

namespace App\Http\Repository\Contracts;

interface NotificationRepositoryInterface
{
    public function index();

    public function create(array $data);

    public function markAsRead(string $id);

    public function destroy(string $id);
}
