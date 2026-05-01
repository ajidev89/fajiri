<?php

namespace App\Http\Repository\Contracts;

interface CampaignRepositoryInterface
{
    public function analytics($request = null);
    public function all($request);
    public function urgentCampaigns();
    public function find($id);
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
    public function types();
    public function donatedCampaigns($request);
}
