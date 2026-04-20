<?php

namespace App\Http\Repository\Contracts;


interface AnalyticsRepositoryInterface{
    public function index($request = null);
    public function donationChartlyAnnualy($request = null);
    public function topPerformingCampaigns($request = null);
    public function leaderboard();
    public function disbursementStats();
}