<?php

namespace App\Providers;

use App\Http\Repository\AuthRepository;
use App\Http\Repository\Contracts\AuthRepositoryInterface;
use App\Http\Repository\Contracts\CountryRepositoryInterface;
use App\Http\Repository\Contracts\GoogleRepositoryInterface;
use App\Http\Repository\Contracts\KycRepositoryInterface;
use App\Http\Repository\Contracts\NeedRepositoryInterface;
use App\Http\Repository\Contracts\OtpRepositoryInterface;
use App\Http\Repository\Contracts\PlanRepositoryInterface;
use App\Http\Repository\Contracts\NotificationRepositoryInterface;
use App\Http\Repository\Contracts\PreferenceRepositoryInterface;
use App\Http\Repository\Contracts\UserRepositoryInterface;
use App\Http\Repository\CountryRepository;
use App\Http\Repository\GoogleRepository;
use App\Http\Repository\KycRepository;
use App\Http\Repository\OtpRepository;
use App\Http\Repository\PlanRepository;
use App\Http\Repository\NotificationRepository;
use App\Http\Repository\PreferenceRepository;
use App\Http\Repository\UserRepository;
use App\Http\Repository\CampaignRepository;
use App\Http\Repository\DonationRepository;
use App\Http\Repository\Contracts\CampaignRepositoryInterface;
use App\Http\Repository\Contracts\DonationRepositoryInterface;
use App\Http\Repository\Contracts\PaymentRepositoryInterface;
use App\Http\Repository\Contracts\InitiativeRepositoryInterface;
use App\Http\Repository\PaymentRepository;
use App\Http\Repository\InitiativeRepository;
use Illuminate\Support\ServiceProvider;
use App\Http\Repository\Contracts\InsuranceRepositoryInterface;
use App\Http\Repository\InsuranceRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    public $bindings = [
        CountryRepositoryInterface::class =>  CountryRepository::class,
        AuthRepositoryInterface::class => AuthRepository::class,
        OtpRepositoryInterface::class => OtpRepository::class,
        UserRepositoryInterface::class => UserRepository::class,
        KycRepositoryInterface::class => KycRepository::class,
        GoogleRepositoryInterface::class => GoogleRepository::class,
        CampaignRepositoryInterface::class => CampaignRepository::class,
        DonationRepositoryInterface::class => DonationRepository::class,
        PaymentRepositoryInterface::class => PaymentRepository::class,
        NotificationRepositoryInterface::class => NotificationRepository::class,
        PlanRepositoryInterface::class => PlanRepository::class,
        InitiativeRepositoryInterface::class => InitiativeRepository::class,
        PreferenceRepositoryInterface::class => PreferenceRepository::class,
        InsuranceRepositoryInterface::class => InsuranceRepository::class,
        NeedRepositoryInterface::class => \App\Http\Repository\NeedRepository::class,
    ];
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
    
    }
}
