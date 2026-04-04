<?php

namespace App\Http\Repository\Contracts;


interface AnalyticsRepositoryInterface{
    public function index();
    public function donationChartlyAnnualy();
    public function topPerformingCampaigns();
}