<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\CampaignRepositoryInterface;
use App\Http\Repository\Contracts\DonationRepositoryInterface;
use App\Http\Requests\Campaign\CampaignRequest;
use App\Http\Requests\Campaign\DonationRequest;
use App\Http\Resources\CampaignResource;
use Illuminate\Http\Request;

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
        $data['user_id'] = auth()->id();
        $campaign = $this->campaignRepository->create($data);
        return new CampaignResource($campaign);
    }

    public function show($id)
    {
        $campaign = $this->campaignRepository->find($id);
        $campaign->load('user');
        return new CampaignResource($campaign);
    }

    public function update(CampaignRequest $request, $id)
    {
        $campaign = $this->campaignRepository->find($id);
        
        if ($campaign->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $campaign = $this->campaignRepository->update($id, $request->validated());
        return new CampaignResource($campaign);
    }

    public function destroy($id)
    {
        $campaign = $this->campaignRepository->find($id);

        if ($campaign->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->campaignRepository->delete($id);
        return response()->json(['message' => 'Campaign deleted successfully']);
    }

    public function donate(DonationRequest $request, $id)
    {
        $campaign = $this->campaignRepository->find($id);

        $donation = $this->donationRepository->create([
            'campaign_id' => $campaign->id,
            'user_id' => auth()->id(),
            'amount' => $request->amount,
            'status' => 'completed', // For simplicity, marking as completed immediately. In a real app, this would involve a payment gateway.
        ]);

        return response()->json([
            'message' => 'Donation successful',
            'donation' => $donation,
            'campaign' => new CampaignResource($campaign->fresh())
        ]);
    }
}
