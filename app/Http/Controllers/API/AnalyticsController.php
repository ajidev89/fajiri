<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Repository\Contracts\AnalyticsRepositoryInterface;

class AnalyticsController extends Controller
{
    public function __construct(protected AnalyticsRepositoryInterface $analyticsRepository){

    }

    public function index(Request $request){
        $data = $this->analyticsRepository->index($request);
        return $this->handleSuccessResponse("Analytics", $data);
    }

    public function donationChartlyAnnualy(Request $request){
        $data = $this->analyticsRepository->donationChartlyAnnualy($request);
        return $this->handleSuccessResponse("Donation Chartly Annualy", $data);
    }

    public function topPerformingCampaigns(Request $request){
        $data = $this->analyticsRepository->topPerformingCampaigns($request);
        return $this->handleSuccessResponse("Top Performing Campaigns", $data);
    }

    public function leaderboard(){
        $data = $this->analyticsRepository->leaderboard();
        return $this->handleSuccessResponse("Successfully fetched leaderboard", $data);
    }

    public function disbursementStats(){
        $data = $this->analyticsRepository->disbursementStats();
        return $this->handleSuccessResponse("Disbursement Analytics", $data);
    }
}
