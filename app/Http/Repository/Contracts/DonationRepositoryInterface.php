<?php

namespace App\Http\Repository\Contracts;

interface DonationRepositoryInterface
{
    public function index();
    public function create(array $data);
    public function findByDonatable(string $type, $id);
    public function findByReference(string $reference);
    public function leaderboard(int $limit = 10, ?string $donatableType = null, $donatableId = null);
}
