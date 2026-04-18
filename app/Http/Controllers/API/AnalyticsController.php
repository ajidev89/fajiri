<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Repository\Contracts\AnalyticsRepositoryInterface;

class AnalyticsController extends Controller
{
    public function __construct(protected AnalyticsRepositoryInterface $analyticsRepository){

    }

    public function index(){
        $data = $this->analyticsRepository->index();
        return $this->handleSuccessResponse("Analytics", $data);
    }

    public function donationChartlyAnnualy(){
        $data = $this->analyticsRepository->donationChartlyAnnualy();
        return $this->handleSuccessResponse("Donation Chartly Annualy", $data);
    }

    public function topPerformingCampaigns(){
        $data = $this->analyticsRepository->topPerformingCampaigns();
        return $this->handleSuccessResponse("Top Performing Campaigns", $data);
    }

    public function leaderboard(){
        $data = $this->analyticsRepository->leaderboard();
        return $this->handleSuccessResponse("Successful fetched leaderboard", $data);
    }
}
