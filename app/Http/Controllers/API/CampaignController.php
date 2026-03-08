<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\CampaignRepositoryInterface;
use App\Http\Repository\Contracts\DonationRepositoryInterface;
use App\Http\Requests\Campaign\CampaignRequest;
use App\Http\Requests\Campaign\DonationRequest;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
    public function __construct(
        protected CampaignRepositoryInterface $campaignRepository,
        protected DonationRepositoryInterface $donationRepository
    ) {}

    public function index()
    {
        $campaigns = $this->campaignRepository->all();
        return CampaignResource::collection($campaigns);
    }

    public function store(CampaignRequest $request)
    {
        $data = $request->validated();
        $data['added_by'] = auth()->id();
        $campaign = $this->campaignRepository->create($data);
        return new CampaignResource($campaign);
    }

    public function show($id)
    {
        $campaign = $this->campaignRepository->find($id);
        $campaign->load('addedBy');
        return new CampaignResource($campaign);
    }

    public function update(CampaignRequest $request,Campaign $campaign)
    {
        $campaign = $this->campaignRepository->update($campaign->id, $request->validated());
        return new CampaignResource($campaign);
    }

    public function destroy(Campaign $campaign)
    {

        if ($campaign->added_by !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->campaignRepository->delete($campaign->id);
        return response()->json(['message' => 'Campaign deleted successfully']);
    }
}
